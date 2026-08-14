<?php

use App\Models\ForumComment;
use App\Models\ForumPost;
use App\Models\ForumTopic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

beforeEach(function () {
    config()->set('auth.guards.api', [
        'driver' => 'session',
        'provider' => 'users',
    ]);
});

it('lists only active public topics', function () {
    ForumTopic::create([
        'name' => 'Medical Imaging',
        'is_active' => true,
        'is_locked' => false,
        'sort_order' => 1,
    ]);

    ForumTopic::create([
        'name' => 'Materials Science',
        'is_active' => true,
        'is_locked' => false,
        'sort_order' => 2,
    ]);

    ForumTopic::create([
        'name' => 'Hidden Topic',
        'is_active' => false,
        'is_locked' => false,
        'sort_order' => 3,
    ]);

    $response = $this->getJson('/api/v1/forum/topics');

    $response->assertOk()->assertJson(['success' => true]);

    $topics = $response->json('data');

    expect($topics)->toHaveCount(2);
    expect(collect($topics)->pluck('name')->all())->toBe([
        'Medical Imaging',
        'Materials Science',
    ]);
});

it('lists only published posts and returns pagination metadata', function () {
    $author = User::factory()->create();

    $topic = ForumTopic::create([
        'name' => 'Segmentation',
        'is_active' => true,
        'is_locked' => false,
    ]);

    foreach (range(1, 3) as $index) {
        ForumPost::create([
            'user_id' => $author->id,
            'topic_id' => $topic->id,
            'title' => "Published Post {$index}",
            'body' => "Published post body {$index} with enough content to be listed publicly.",
            'status' => 'published',
            'created_at' => now()->subMinutes($index),
            'updated_at' => now()->subMinutes($index),
        ]);
    }

    ForumPost::create([
        'user_id' => $author->id,
        'topic_id' => $topic->id,
        'title' => 'Pending Post',
        'body' => 'This post should not appear in the public feed.',
        'status' => 'pending',
    ]);

    ForumPost::create([
        'user_id' => $author->id,
        'topic_id' => $topic->id,
        'title' => 'Hidden Post',
        'body' => 'This post should not appear in the public feed either.',
        'status' => 'hidden',
    ]);

    $response = $this->getJson('/api/v1/forum/posts?per_page=2');

    $response->assertOk()->assertJson([
        'success' => true,
        'data' => [
            'current_page' => 1,
            'last_page' => 2,
            'per_page' => 2,
            'total' => 3,
        ],
    ]);

    $posts = $response->json('data.data');

    expect($posts)->toHaveCount(2);
    expect(collect($posts)->pluck('status')->unique()->all())->toBe(['published']);
});

it('filters public posts by topic slug and featured flag', function () {
    $author = User::factory()->create();

    $targetTopic = ForumTopic::create([
        'name' => 'Reconstruction',
        'is_active' => true,
        'is_locked' => false,
    ]);

    $otherTopic = ForumTopic::create([
        'name' => 'Visualization',
        'is_active' => true,
        'is_locked' => false,
    ]);

    $matchingPost = ForumPost::create([
        'user_id' => $author->id,
        'topic_id' => $targetTopic->id,
        'title' => 'Featured reconstruction workflow',
        'body' => 'This post should match both the topic and featured filters.',
        'status' => 'published',
        'is_featured' => true,
    ]);

    ForumPost::create([
        'user_id' => $author->id,
        'topic_id' => $targetTopic->id,
        'title' => 'Non featured reconstruction workflow',
        'body' => 'This post should be excluded by the featured filter.',
        'status' => 'published',
        'is_featured' => false,
    ]);

    ForumPost::create([
        'user_id' => $author->id,
        'topic_id' => $otherTopic->id,
        'title' => 'Featured visualization workflow',
        'body' => 'This post should be excluded by the topic filter.',
        'status' => 'published',
        'is_featured' => true,
    ]);

    $response = $this->getJson("/api/v1/forum/posts?topic={$targetTopic->slug}&featured=1");

    $response->assertOk()->assertJson(['success' => true]);

    $posts = $response->json('data.data');

    expect($posts)->toHaveCount(1);
    expect($posts[0]['id'])->toBe($matchingPost->id);
    expect($posts[0]['topic']['slug'])->toBe($targetTopic->slug);
});

it('returns only visible top level comments with visible replies for public post threads', function () {
    $author = User::factory()->create();

    $topic = ForumTopic::create([
        'name' => 'Forum Threads',
        'is_active' => true,
        'is_locked' => false,
    ]);

    $post = ForumPost::create([
        'user_id' => $author->id,
        'topic_id' => $topic->id,
        'title' => 'Threaded discussion',
        'body' => 'A public post with nested comments.',
        'status' => 'published',
    ]);

    $visibleParent = ForumComment::create([
        'user_id' => $author->id,
        'post_id' => $post->id,
        'body' => 'Visible parent comment',
        'status' => 'visible',
    ]);

    ForumComment::create([
        'user_id' => $author->id,
        'post_id' => $post->id,
        'body' => 'Hidden parent comment',
        'status' => 'hidden',
    ]);

    ForumComment::create([
        'user_id' => $author->id,
        'post_id' => $post->id,
        'parent_id' => $visibleParent->id,
        'body' => 'Visible reply',
        'status' => 'visible',
    ]);

    ForumComment::create([
        'user_id' => $author->id,
        'post_id' => $post->id,
        'parent_id' => $visibleParent->id,
        'body' => 'Hidden reply',
        'status' => 'hidden',
    ]);

    $response = $this->getJson("/api/v1/forum/posts/{$post->slug}/comments?per_page=10");

    $response->assertOk()->assertJson([
        'success' => true,
        'data' => [
            'total' => 1,
        ],
    ]);

    $comments = $response->json('data.data');

    expect($comments)->toHaveCount(1);
    expect($comments[0]['body'])->toBe('Visible parent comment');
    expect($comments[0]['replies'])->toHaveCount(1);
    expect($comments[0]['replies'][0]['body'])->toBe('Visible reply');
});

it('shows published public posts by slug and increments the view count', function () {
    $author = User::factory()->create();

    $topic = ForumTopic::create([
        'name' => 'Public Detail',
        'is_active' => true,
        'is_locked' => false,
    ]);

    $post = ForumPost::create([
        'user_id' => $author->id,
        'topic_id' => $topic->id,
        'title' => 'Public detail page',
        'body' => 'This published post should be retrievable by slug.',
        'status' => 'published',
        'view_count' => 0,
    ]);

    $hiddenPost = ForumPost::create([
        'user_id' => $author->id,
        'topic_id' => $topic->id,
        'title' => 'Hidden detail page',
        'body' => 'This hidden post should not be retrievable by slug.',
        'status' => 'hidden',
    ]);

    $okResponse = $this->getJson("/api/v1/forum/posts/{$post->slug}");
    $missingResponse = $this->getJson("/api/v1/forum/posts/{$hiddenPost->slug}");

    $okResponse->assertOk()->assertJson([
        'success' => true,
        'data' => [
            'id' => $post->id,
            'slug' => $post->slug,
            'status' => 'published',
        ],
    ]);

    $missingResponse->assertStatus(404)->assertJson([
        'success' => false,
        'message' => 'Post not found.',
    ]);

    expect($post->fresh()->view_count)->toBe(1);
});
