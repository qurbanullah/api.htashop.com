<?php

use App\Mail\Newsletter\NewsletterWelcomeMail;
use App\Models\Subscribe;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function makeNewsletterTenant(): Tenant
{
    return Tenant::query()->create([
        'name' => 'Newsletter Store',
        'slug' => 'newsletter-store-' . Str::uuid(),
        'domain' => 'newsletter-store.example.com',
        'is_active' => true,
    ]);
}

it('subscribes a guest email to the store newsletter', function () {
    $tenant = makeNewsletterTenant();

    $response = $this->postJson('/api/v1/newsletter/subscribe', [
        'email' => 'newsletter@example.com',
        'consent' => true,
        'source_page' => 'https://htashop.com/',
    ], ['X-Tenant-Domain' => 'newsletter-store.example.com']);

    $response->assertCreated()->assertJson([
        'success' => true,
        'data' => [
            'email' => 'newsletter@example.com',
            'is_subscribed' => true,
        ],
    ]);

    $row = Subscribe::query()->first();

    expect($row)->not->toBeNull();
    expect($row->type)->toBe('newsletter');
    expect($row->tenant_id)->toBe($tenant->id);
    expect($row->email)->toBe('newsletter@example.com');
    expect($row->subscribable_type)->toBeNull();
    expect($row->is_subscribed)->toBeTrue();
    expect($row->unsubscribe_token)->not->toBeNull();
});

it('does not create duplicates for the same email and tenant', function () {
    makeNewsletterTenant();

    $payload = ['email' => 'repeat@example.com', 'consent' => true];
    $headers = ['X-Tenant-Domain' => 'newsletter-store.example.com'];

    $this->postJson('/api/v1/newsletter/subscribe', $payload, $headers)->assertCreated();
    $this->postJson('/api/v1/newsletter/subscribe', $payload, $headers)
        ->assertOk()
        ->assertJsonPath('data.is_subscribed', true);

    expect(Subscribe::query()->where('email', 'repeat@example.com')->count())->toBe(1);
});

it('re-subscribes an email that previously unsubscribed', function () {
    $tenant = makeNewsletterTenant();

    $existing = Subscribe::query()->create([
        'type' => 'newsletter',
        'tenant_id' => $tenant->id,
        'email' => 'back@example.com',
        'is_subscribed' => false,
        'unsubscribed_at' => now(),
    ]);

    $response = $this->postJson('/api/v1/newsletter/subscribe', [
        'email' => 'back@example.com',
        'consent' => true,
    ], ['X-Tenant-Domain' => 'newsletter-store.example.com']);

    $response->assertOk()->assertJsonPath('data.is_subscribed', true);

    $existing->refresh();

    expect($existing->is_subscribed)->toBeTrue();
    expect($existing->unsubscribed_at)->toBeNull();
});

it('validates the newsletter email address', function () {
    makeNewsletterTenant();

    $this->postJson('/api/v1/newsletter/subscribe', [
        'email' => 'not-an-email',
    ], ['X-Tenant-Domain' => 'newsletter-store.example.com'])
        ->assertStatus(422)
        ->assertJson(['success' => false])
        ->assertJsonPath('message', fn (string $message) => str_contains(strtolower($message), 'email'));
});

it('queues a welcome email on first subscription', function () {
    makeNewsletterTenant();
    Mail::fake();

    $this->postJson('/api/v1/newsletter/subscribe', [
        'email' => 'welcome@example.com',
        'consent' => true,
    ], ['X-Tenant-Domain' => 'newsletter-store.example.com'])->assertCreated();

    Mail::assertQueued(NewsletterWelcomeMail::class, function (NewsletterWelcomeMail $mail) {
        return $mail->subscription->email === 'welcome@example.com';
    });
});

it('does not queue a welcome email when disabled or already subscribed', function () {
    makeNewsletterTenant();
    Mail::fake();
    config()->set('mail.newsletter_welcome_enabled', false);

    $headers = ['X-Tenant-Domain' => 'newsletter-store.example.com'];

    $this->postJson('/api/v1/newsletter/subscribe', [
        'email' => 'quiet@example.com',
        'consent' => true,
    ], $headers)->assertCreated();

    Mail::assertNotQueued(NewsletterWelcomeMail::class);
});

it('renders and processes the token unsubscribe flow for newsletter rows', function () {
    $tenant = makeNewsletterTenant();

    $subscription = Subscribe::query()->create([
        'type' => 'newsletter',
        'tenant_id' => $tenant->id,
        'email' => 'unsub@example.com',
        'is_subscribed' => true,
        'subscribed_at' => now(),
    ]);

    $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);

    $this->get(route('unsubscribe.show', $subscription->unsubscribe_token))
        ->assertOk()
        ->assertSee('unsub@example.com');

    $this->post(route('unsubscribe', $subscription->unsubscribe_token))
        ->assertRedirect(route('unsubscribe.success', $subscription->unsubscribe_token));

    expect($subscription->fresh()->is_subscribed)->toBeFalse();

    $this->post(route('unsubscribe.resubscribe', $subscription->unsubscribe_token));

    expect($subscription->fresh()->is_subscribed)->toBeTrue();
});
