<?php

namespace Tests\Feature\Newsletter;

use App\Models\NewsletterSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('subscribes a user to the newsletter', function () {
    $user = User::factory()->create();

    $subscription = $user->subscribeTo('newsletter');

    expect($subscription)->toBeInstanceOf(NewsletterSubscription::class);
    expect($subscription->type)->toBe('newsletter');
    expect($subscription->is_subscribed)->toBeTrue();
    expect($subscription->unsubscribe_token)->not->toBeNull();
    expect($user->isSubscribedTo('newsletter'))->toBeTrue();
});

it('does not create duplicate subscriptions', function () {
    $user = User::factory()->create();

    $user->subscribeTo('newsletter');
    $user->subscribeTo('newsletter');

    expect(NewsletterSubscription::where('subscribeable_id', $user->id)->count())->toBe(1);
});

it('unsubscribes and resubscribes a user', function () {
    $user = User::factory()->create();
    $subscription = $user->subscribeTo('newsletter');

    $subscription->unsubscribe();

    expect($subscription->fresh()->is_subscribed)->toBeFalse();
    expect($user->isSubscribedTo('newsletter'))->toBeFalse();

    $subscription->subscribe();

    expect($subscription->fresh()->is_subscribed)->toBeTrue();
    expect($subscription->fresh()->unsubscribed_at)->toBeNull();
});

it('finds a subscription by its unsubscribe token', function () {
    $user = User::factory()->create();
    $subscription = $user->subscribeTo('newsletter');

    $found = NewsletterSubscription::where('unsubscribe_token', $subscription->unsubscribe_token)->first();

    expect($found)->not->toBeNull();
    expect($found->subscribeable->id)->toBe($user->id);
});
