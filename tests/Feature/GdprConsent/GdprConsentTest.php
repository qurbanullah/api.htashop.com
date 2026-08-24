<?php

namespace Tests\Feature\GdprConsent;

use App\Models\GdprConsent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('records a consent choice for a guest', function () {
    $response = $this->postJson('/api/v1/gdpr/consents', [
        'consent_token' => 'tok-guest-123',
        'categories' => [
            'necessary' => true,
            'preferences' => false,
            'analytics' => true,
            'marketing' => false,
        ],
        'policy_version' => '1.0',
        'source' => 'banner',
    ]);

    $response->assertCreated()->assertJson([
        'success' => true,
        'message' => 'Consent recorded successfully',
    ]);

    $consent = GdprConsent::query()->first();

    expect($consent)->not->toBeNull();
    expect($consent->consent_token)->toBe('tok-guest-123');
    expect($consent->user_id)->toBeNull();
    expect($consent->categories['analytics'])->toBeTrue();
    expect($consent->categories['marketing'])->toBeFalse();
    expect($consent->policy_version)->toBe('1.0');
    expect($consent->source)->toBe('banner');
    expect($consent->accepted_at)->not->toBeNull();
});

it('links consent to an authenticated user when a token is provided', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'api')
        ->postJson('/api/v1/gdpr/consents', [
            'consent_token' => 'tok-user-456',
            'categories' => [
                'necessary' => true,
                'preferences' => true,
                'analytics' => true,
                'marketing' => true,
            ],
            'policy_version' => '1.0',
            'source' => 'settings',
        ])
        ->assertCreated();

    expect(GdprConsent::query()->first()->user_id)->toBe($user->id);
});

it('validates the consent payload', function () {
    $this->postJson('/api/v1/gdpr/consents', [
        'consent_token' => '',
        'categories' => [],
        'source' => 'unknown',
    ])->assertStatus(422);
});

it('returns the latest consent for a token', function () {
    GdprConsent::create([
        'consent_token' => 'tok-latest',
        'categories' => ['necessary' => true, 'preferences' => false, 'analytics' => false, 'marketing' => false],
        'policy_version' => '1.0',
        'source' => 'banner',
        'accepted_at' => now()->subDay(),
    ]);
    GdprConsent::create([
        'consent_token' => 'tok-latest',
        'categories' => ['necessary' => true, 'preferences' => false, 'analytics' => true, 'marketing' => false],
        'policy_version' => '1.0',
        'source' => 'settings',
        'accepted_at' => now(),
    ]);

    $this->getJson('/api/v1/gdpr/consents/latest?consent_token=tok-latest')
        ->assertOk()
        ->assertJsonPath('data.categories.analytics', true)
        ->assertJsonPath('data.source', 'settings');
});

it('returns null data when no consent exists yet', function () {
    $this->getJson('/api/v1/gdpr/consents/latest?consent_token=tok-missing')
        ->assertOk()
        ->assertJsonPath('data', null);
});

it('withdraws consent for a token', function () {
    GdprConsent::create([
        'consent_token' => 'tok-withdraw',
        'categories' => ['necessary' => true, 'preferences' => false, 'analytics' => true, 'marketing' => false],
        'policy_version' => '1.0',
        'source' => 'banner',
        'accepted_at' => now(),
    ]);

    $this->deleteJson('/api/v1/gdpr/consents?consent_token=tok-withdraw')
        ->assertOk()
        ->assertJsonPath('data.deleted', 1);

    expect(GdprConsent::count())->toBe(0);
});
