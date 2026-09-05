<?php

use App\Models\Post;
use App\Models\Subscribe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('post.show renders a published post by slug', function () {
    $post = Post::factory()->published()->create([
        'slug' => 'hello-world',
        'title' => 'Hello World Post',
        'content' => '<p>Post body content</p>',
    ]);

    $this->get(route('post.show', 'hello-world'))
        ->assertOk()
        ->assertSee('Hello World Post')
        ->assertSee('Post body content');
});

test('post.show returns 404 for a post that is not published', function () {
    Post::factory()->draft()->create(['slug' => 'draft-post']);

    $this->get(route('post.show', 'draft-post'))->assertNotFound();
});

test('post.view renders a token-gated post for a subscribed recipient', function () {
    $user = User::factory()->create();
    $post = Post::factory()->create([
        'uuid' => 'view-uuid-1234',
        'slug' => 'private-post',
        'title' => 'Private Post Title',
        'created_by' => $user->id,
    ]);

    Subscribe::create([
        'subscribable_type' => User::class,
        'subscribable_id' => $user->id,
        'type' => 'post',
        'is_subscribed' => true,
        'subscribed_at' => now(),
    ]);

    $token = hash('sha256', $post->uuid . $user->email . config('app.key'));

    $this->get(route('post.view', ['uuid' => $post->uuid, 'token' => $token]))
        ->assertOk()
        ->assertSee('Private Post Title');
});

test('post.view rejects an invalid token', function () {
    $user = User::factory()->create();
    $post = Post::factory()->create(['uuid' => 'view-uuid-5678', 'created_by' => $user->id]);

    $this->get(route('post.view', ['uuid' => $post->uuid, 'token' => 'not-a-valid-token']))
        ->assertForbidden();
});

test('unsubscribe flow shows the page, unsubscribes, and confirms', function () {
    $user = User::factory()->create();
    $subscription = Subscribe::create([
        'subscribable_type' => User::class,
        'subscribable_id' => $user->id,
        'type' => 'post',
        'is_subscribed' => true,
        'subscribed_at' => now(),
    ]);

    $this->get(route('unsubscribe.show', $subscription->unsubscribe_token))
        ->assertOk()
        ->assertSee('Unsubscribe');

    $this->post(route('unsubscribe', $subscription->unsubscribe_token))
        ->assertRedirect(route('unsubscribe.success', $subscription->unsubscribe_token));

    $this->get(route('unsubscribe.success', $subscription->unsubscribe_token))
        ->assertOk()
        ->assertSee('Subscription Updated');

    expect($subscription->fresh()->is_subscribed)->toBeFalse();
});
