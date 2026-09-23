<?php

use App\Mail\Post\PostMail;
use App\Models\Post;
use App\Models\Subscribe;
use App\Models\User;
use App\Services\Post\PostService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('sends a newsletter post to subscribed newsletter emails only', function () {
    Mail::fake();

    $author = User::factory()->create();

    $subscribedA = Subscribe::query()->create([
        'type' => 'newsletter',
        'email' => 'a@example.com',
        'is_subscribed' => true,
        'subscribed_at' => now(),
    ]);

    $subscribedB = Subscribe::query()->create([
        'type' => 'newsletter',
        'email' => 'b@example.com',
        'is_subscribed' => true,
        'subscribed_at' => now(),
    ]);

    Subscribe::query()->create([
        'type' => 'newsletter',
        'email' => 'out@example.com',
        'is_subscribed' => false,
        'unsubscribed_at' => now(),
    ]);

    $post = Post::query()->create([
        'type' => 'newsletter',
        'title' => 'March Newsletter',
        'slug' => 'march-newsletter-' . Str::uuid(),
        'content' => '<p>Hello subscribers!</p>',
        'status' => 'draft',
        'created_by' => $author->id,
    ]);

    $result = app(PostService::class)->sendPost($post);

    expect($result['sent_count'])->toBe(2);
    expect($result['total_recipients'])->toBe(2);

    $post->refresh();

    expect($post->status->value)->toBe('sent');
    expect($post->sent_count)->toBe(2);
    expect($post->recipients_count)->toBe(2);
    expect($post->sent_at)->not->toBeNull();

    Mail::assertQueued(PostMail::class, 2);
    Mail::assertQueued(PostMail::class, function (PostMail $mail) use ($subscribedA) {
        return $mail->hasTo('a@example.com')
            && str_contains($mail->render(), '/unsubscribe/' . $subscribedA->unsubscribe_token);
    });
    Mail::assertQueued(PostMail::class, function (PostMail $mail) use ($subscribedB) {
        return $mail->hasTo('b@example.com');
    });
});

it('throws when no newsletter subscribers exist', function () {
    Mail::fake();

    $author = User::factory()->create();

    $post = Post::query()->create([
        'type' => 'newsletter',
        'title' => 'Empty Newsletter',
        'slug' => 'empty-newsletter-' . Str::uuid(),
        'content' => '<p>Nobody here yet.</p>',
        'status' => 'draft',
        'created_by' => $author->id,
    ]);

    expect(fn () => app(PostService::class)->sendPost($post))
        ->toThrow(\Exception::class, 'No subscribers found');
});
