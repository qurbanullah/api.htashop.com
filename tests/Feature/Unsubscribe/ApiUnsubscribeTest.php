<?php

use App\Models\Subscribe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns subscription status for a valid token', function () {
    $subscription = Subscribe::query()->create([
        'type' => 'newsletter',
        'email' => 'status@example.com',
        'is_subscribed' => true,
        'subscribed_at' => now(),
    ]);

    $this->getJson('/api/v1/unsubscribe/' . $subscription->unsubscribe_token)
        ->assertOk()
        ->assertJson([
            'success' => true,
            'data' => [
                'type' => 'newsletter',
                'type_label' => 'newsletter updates',
                'email' => 'status@example.com',
                'is_subscribed' => true,
            ],
        ]);
});

it('unsubscribes and resubscribes via the JSON API', function () {
    $subscription = Subscribe::query()->create([
        'type' => 'newsletter',
        'email' => 'toggle@example.com',
        'is_subscribed' => true,
        'subscribed_at' => now(),
    ]);
    $token = $subscription->unsubscribe_token;

    $this->postJson('/api/v1/unsubscribe/' . $token . '/unsubscribe')
        ->assertOk()
        ->assertJsonPath('data.is_subscribed', false);

    $this->postJson('/api/v1/unsubscribe/' . $token . '/resubscribe')
        ->assertOk()
        ->assertJsonPath('data.is_subscribed', true);
});

it('falls back to the owning user email for account-based subscriptions', function () {
    $user = User::factory()->create(['email' => 'owner@example.com']);

    $subscription = Subscribe::query()->create([
        'type' => 'post',
        'subscribable_type' => get_class($user),
        'subscribable_id' => $user->id,
        'is_subscribed' => true,
        'subscribed_at' => now(),
    ]);

    $this->getJson('/api/v1/unsubscribe/' . $subscription->unsubscribe_token)
        ->assertOk()
        ->assertJsonPath('data.email', 'owner@example.com')
        ->assertJsonPath('data.type', 'post');
});

it('returns 404 for an unknown token', function () {
    $this->getJson('/api/v1/unsubscribe/not-a-real-token')->assertNotFound();
});
