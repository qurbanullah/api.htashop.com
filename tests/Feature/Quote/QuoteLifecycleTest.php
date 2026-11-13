<?php

namespace Tests\Feature\Quote;

use App\Mail\QuoteDecisionMail;
use App\Mail\QuoteExpiredMail;
use App\Mail\QuoteExpiringMail;
use App\Models\Document;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\QuoteActivity;
use App\Models\QuoteRequest;
use App\Models\QuoteResponse;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

beforeEach(function () {
    Mail::fake();
    Storage::fake('idrivee2');
});

function lifecycleTenant(): Tenant
{
    return Tenant::query()->create([
        'name' => 'Lifecycle Tenant',
        'slug' => 'lc-tenant-'.Str::lower(Str::random(8)),
        'is_active' => true,
    ]);
}

function lifecycleOrganization(Tenant $tenant): Organization
{
    return Organization::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Lifecycle Org',
        'slug' => 'lc-org-'.Str::lower(Str::random(8)),
        'type' => 'vendor',
        'is_active' => true,
    ]);
}

function lifecycleMerchant(Tenant $tenant, Organization $organization): User
{
    $user = User::factory()->create();

    Membership::query()->create([
        'tenant_id' => $tenant->id,
        'organization_id' => $organization->id,
        'user_id' => $user->id,
        'role' => 'owner',
        'is_primary' => true,
        'is_active' => true,
    ]);

    return $user;
}

function lifecycleQuote(array $attributes = []): QuoteRequest
{
    return QuoteRequest::query()->create(array_merge([
        'first_name' => 'Hina',
        'last_name' => 'Raza',
        'email' => 'hina@example.test',
        'organization' => 'Buyer Co',
        'job_title' => 'Buyer',
        'country' => 'Pakistan',
        'city' => 'Lahore',
        'postal_code' => '54000',
        'message' => 'Please quote.',
        'status' => 'replied',
        'requirements' => ['product_name' => 'Stepper Motor', 'sku' => 'MOT-17'],
    ], $attributes));
}

function lifecycleResponse(QuoteRequest $quote, array $attributes = []): QuoteResponse
{
    $response = QuoteResponse::query()->create(array_merge([
        'quote_request_id' => $quote->id,
        'subject' => 'Our offer',
        'message' => 'Please find the quotation attached.',
        'currency' => 'PKR',
        'validity_days' => 30,
        'revision' => 1,
        'status' => 'sent',
        'sent_at' => now(),
        'expires_at' => now()->addDays(5),
        'subtotal' => 1000,
        'total_amount' => 1000,
    ], $attributes));

    $response->items()->create([
        'name' => 'Stepper Motor',
        'sku' => 'MOT-17',
        'quantity' => 2,
        'unit_price' => 500,
        'discount_amount' => 0,
        'tax_rate' => 0,
        'line_total' => 1000,
        'sort_order' => 0,
    ]);

    return $response;
}

describe('quote expiry lifecycle', function () {
    it('reminds the buyer once before the offer lapses', function () {
        $quote = lifecycleQuote();
        $response = lifecycleResponse($quote, ['expires_at' => now()->addDay()]);

        $this->artisan('quotes:expire')->assertSuccessful();

        expect($response->fresh()->expiry_notified_at)->not->toBeNull();
        Mail::assertQueued(QuoteExpiringMail::class);

        // Second run must not remind the same revision again.
        $this->artisan('quotes:expire')->assertSuccessful();

        Mail::assertQueued(QuoteExpiringMail::class, 1);
    });

    it('expires an overdue revision and closes the request', function () {
        $quote = lifecycleQuote(['status' => 'replied']);
        $response = lifecycleResponse($quote, ['expires_at' => now()->subHour()]);

        $this->artisan('quotes:expire')->assertSuccessful();

        expect($response->fresh()->status)->toBe('expired')
            ->and($quote->fresh()->status)->toBe('expired');

        expect(QuoteActivity::query()
            ->where('quote_request_id', $quote->id)
            ->where('type', QuoteActivity::EXPIRED)
            ->exists())->toBeTrue();

        Mail::assertQueued(QuoteExpiredMail::class);
    });

    it('reports changes without writing when run as a dry run', function () {
        $quote = lifecycleQuote();
        $response = lifecycleResponse($quote, ['expires_at' => now()->subHour()]);

        $this->artisan('quotes:expire', ['--dry-run' => true])->assertSuccessful();

        expect($response->fresh()->status)->toBe('sent')
            ->and($quote->fresh()->status)->toBe('replied');

        Mail::assertNothingQueued();
    });
});

describe('quotation pdf', function () {
    it('renders and stores a quotation document for a revision', function () {
        Http::fake([
            '*/forms/chromium/convert/html' => Http::response('%PDF-1.4 quotation', 200),
        ]);

        $tenant = lifecycleTenant();
        $organization = lifecycleOrganization($tenant);
        $merchant = lifecycleMerchant($tenant, $organization);

        $quote = lifecycleQuote([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
        ]);
        $response = lifecycleResponse($quote);

        $this->withToken(signInDevice($merchant->email)['access_token'])
            ->postJson('/api/v1/quotes/'.$quote->uuid.'/responses/'.$response->uuid.'/pdf')
            ->assertCreated()
            ->assertJsonPath('data.type', 'quotation')
            ->assertJsonPath('data.filename', 'quotation-'.strtolower($quote->quote_number).'-r1.pdf');

        $document = Document::query()->where('type', 'quotation')->firstOrFail();

        expect($document->documentable_id)->toBe($quote->id)
            ->and($document->metadata['revision'])->toBe(1);

        Storage::disk('idrivee2')->assertExists($document->object_key);
    });

    it('returns 503 when the PDF service fails', function () {
        Http::fake([
            '*/forms/chromium/convert/html' => Http::response('boom', 500),
        ]);

        $tenant = lifecycleTenant();
        $organization = lifecycleOrganization($tenant);
        $merchant = lifecycleMerchant($tenant, $organization);

        $quote = lifecycleQuote([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
        ]);
        $response = lifecycleResponse($quote);

        $this->withToken(signInDevice($merchant->email)['access_token'])
            ->postJson('/api/v1/quotes/'.$quote->uuid.'/responses/'.$response->uuid.'/pdf')
            ->assertStatus(503);
    });
});

describe('buyer decision notification', function () {
    it('notifies the assigned owner when the buyer accepts', function () {
        $owner = User::factory()->create();

        $quote = lifecycleQuote(['assigned_to' => $owner->id]);
        lifecycleResponse($quote);

        $this->postJson(URL::temporarySignedRoute('quotes.accept', now()->addHour(), ['uuid' => $quote->uuid]))
            ->assertOk();

        Mail::assertQueued(QuoteDecisionMail::class, function (QuoteDecisionMail $mail) use ($owner) {
            return $mail->decision === 'accepted'
                && $mail->hasTo($owner->email);
        });
    });

    it('falls back to the configured mailbox when nobody owns the quote', function () {
        $quote = lifecycleQuote();
        lifecycleResponse($quote);

        $this->postJson(
            URL::temporarySignedRoute('quotes.decline', now()->addHour(), ['uuid' => $quote->uuid]),
            ['reason' => 'Budget'],
        )->assertOk();

        $fallback = config('quotes.notifications.fallback_email');

        Mail::assertQueued(QuoteDecisionMail::class, function (QuoteDecisionMail $mail) use ($fallback) {
            return $mail->decision === 'declined'
                && $mail->reason === 'Budget'
                && $mail->hasTo($fallback);
        });
    });
});
