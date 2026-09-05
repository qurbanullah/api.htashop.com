<?php

namespace Tests\Feature\Subscribe;

use App\Models\Subscribe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('subscribes a user to post notifications', function () {
    $user = User::factory()->create();

    $subscription = $user->subscribeTo('post');

    expect($subscription)->toBeInstanceOf(Subscribe::class);
    expect($subscription->type)->toBe('post');
    expect($subscription->is_subscribed)->toBeTrue();
    expect($subscription->unsubscribe_token)->not->toBeNull();
    expect($user->isSubscribedTo('post'))->toBeTrue();
});

it('does not create duplicate subscriptions', function () {
    $user = User::factory()->create();

    $user->subscribeTo('post');
    $user->subscribeTo('post');

    expect(Subscribe::where('subscribable_id', $user->id)->count())->toBe(1);
});

it('unsubscribes and resubscribes a user', function () {
    $user = User::factory()->create();
    $subscription = $user->subscribeTo('post');

    $subscription->unsubscribe();

    expect($subscription->fresh()->is_subscribed)->toBeFalse();
    expect($user->isSubscribedTo('post'))->toBeFalse();

    $subscription->subscribe();

    expect($subscription->fresh()->is_subscribed)->toBeTrue();
    expect($subscription->fresh()->unsubscribed_at)->toBeNull();
});

it('finds a subscription by its unsubscribe token', function () {
    $user = User::factory()->create();
    $subscription = $user->subscribeTo('post');

    $found = Subscribe::where('unsubscribe_token', $subscription->unsubscribe_token)->first();

    expect($found)->not->toBeNull();
    expect($found->subscribable->id)->toBe($user->id);
});
