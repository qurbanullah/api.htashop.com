<?php

use App\Http\Middleware\ApiAuthenticate;
use App\Models\ForumComment;
use App\Models\ForumLike;
use App\Models\ForumPost;
use App\Models\ForumReport;
use App\Models\ForumTopic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('admin', 'api');
    config()->set('auth.guards.api', [
        'driver' => 'session',
        'provider' => 'users',
    ]);
    $this->withoutMiddleware(ApiAuthenticate::class);
});

it('allows authenticated users to create posts in active unlocked topics', function () {
    $user = User::factory()->create();

    $topic = ForumTopic::create([
        'name' => 'Deep Learning',
        'description' => 'Neural network discussions',
        'is_active' => true,
        'is_locked' => false,
    ]);

    $this->actingAs($user, 'api');

    $response = $this->postJson('/api/v1/forum/posts', [
        'topic_uuid' => $topic->uuid,
        'title' => 'How should we benchmark segmentation models?',
        'body' => 'I want to compare multiple segmentation models with a consistent evaluation protocol.',
    ]);

    $response->assertCreated()->assertJson([
        'success' => true,
        'data' => [
            'status' => 'published',
            'title' => 'How should we benchmark segmentation models?',
        ],
    ]);

    expect(ForumPost::count())->toBe(1);
    expect($topic->fresh()->post_count)->toBe(1);
});

it('blocks post creation in inactive or locked topics', function () {
    $user = User::factory()->create();

    $inactiveTopic = ForumTopic::create([
        'name' => 'Archived Topic',
        'is_active' => false,
        'is_locked' => false,
    ]);

    $lockedTopic = ForumTopic::create([
        'name' => 'Locked Topic',
        'is_active' => true,
        'is_locked' => true,
    ]);

    $this->actingAs($user, 'api');

    $inactiveResponse = $this->postJson('/api/v1/forum/posts', [
        'topic_uuid' => $inactiveTopic->uuid,
        'title' => 'Inactive topic post attempt',
        'body' => 'This should not be accepted because the topic is inactive.',
    ]);

    $lockedResponse = $this->postJson('/api/v1/forum/posts', [
        'topic_uuid' => $lockedTopic->uuid,
        'title' => 'Locked topic post attempt',
        'body' => 'This should not be accepted because the topic is locked.',
    ]);

    $inactiveResponse->assertStatus(422)->assertJson([
        'success' => false,
        'message' => 'Cannot create posts in this topic.',
    ]);

    $lockedResponse->assertStatus(422)->assertJson([
        'success' => false,
        'message' => 'Cannot create posts in this topic.',
    ]);

    expect(ForumPost::count())->toBe(0);
});

it('accepts reports for visible content and rejects missing or hidden targets', function () {
    $reporter = User::factory()->create();
    $author = User::factory()->create();

    $topic = ForumTopic::create([
        'name' => 'QA',
        'is_active' => true,
        'is_locked' => false,
    ]);

    $post = ForumPost::create([
        'user_id' => $author->id,
        'topic_id' => $topic->id,
        'title' => 'Published post',
        'body' => 'A published forum post for reporting tests.',
        'status' => 'published',
    ]);

    $visibleComment = ForumComment::create([
        'user_id' => $author->id,
        'post_id' => $post->id,
        'body' => 'Visible comment',
        'status' => 'visible',
    ]);

    $hiddenComment = ForumComment::create([
        'user_id' => $author->id,
        'post_id' => $post->id,
        'body' => 'Hidden comment',
        'status' => 'hidden',
    ]);

    $this->actingAs($reporter, 'api');

    $validResponse = $this->postJson('/api/v1/forum/report', [
        'reportable_type' => 'comment',
        'reportable_id' => $visibleComment->id,
        'reason' => 'spam',
        'description' => 'This looks automated.',
    ]);

    $missingResponse = $this->postJson('/api/v1/forum/report', [
        'reportable_type' => 'post',
        'reportable_id' => 999999,
        'reason' => 'spam',
    ]);

    $hiddenResponse = $this->postJson('/api/v1/forum/report', [
        'reportable_type' => 'comment',
        'reportable_id' => $hiddenComment->id,
        'reason' => 'spam',
    ]);

    $validResponse->assertCreated()->assertJson([
        'success' => true,
        'message' => 'Report submitted. Our team will review it.',
    ]);

    $missingResponse->assertStatus(422);
    $hiddenResponse->assertStatus(422);

    expect(ForumReport::count())->toBe(1);
    expect(ForumReport::first()->reportable_id)->toBe($visibleComment->id);
});

it('removes likes reports and comments when a post is deleted', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();

    $topic = ForumTopic::create([
        'name' => 'Support',
        'is_active' => true,
        'is_locked' => false,
    ]);

    $post = ForumPost::create([
        'user_id' => $owner->id,
        'topic_id' => $topic->id,
        'title' => 'Delete me cleanly',
        'body' => 'This post should remove all attached forum artifacts on delete.',
        'status' => 'published',
    ]);

    $comment = ForumComment::create([
        'user_id' => $otherUser->id,
        'post_id' => $post->id,
        'body' => 'A comment that should be deleted too.',
        'status' => 'visible',
    ]);

    ForumLike::create([
        'user_id' => $otherUser->id,
        'likeable_id' => $post->id,
        'likeable_type' => ForumPost::class,
    ]);

    ForumLike::create([
        'user_id' => $owner->id,
        'likeable_id' => $comment->id,
        'likeable_type' => ForumComment::class,
    ]);

    ForumReport::create([
        'user_id' => $otherUser->id,
        'reportable_id' => $post->id,
        'reportable_type' => ForumPost::class,
        'reason' => 'spam',
        'status' => 'pending',
    ]);

    ForumReport::create([
        'user_id' => $owner->id,
        'reportable_id' => $comment->id,
        'reportable_type' => ForumComment::class,
        'reason' => 'other',
        'status' => 'pending',
    ]);

    $topic->update(['post_count' => 1]);

    $this->actingAs($owner, 'api');

    $response = $this->deleteJson("/api/v1/forum/posts/{$post->id}");

    $response->assertOk()->assertJson(['success' => true]);

    $this->assertSoftDeleted('forum_posts', ['id' => $post->id]);
    $this->assertSoftDeleted('forum_comments', ['id' => $comment->id]);
    $this->assertDatabaseMissing('forum_likes', ['likeable_type' => ForumPost::class, 'likeable_id' => $post->id]);
    $this->assertDatabaseMissing('forum_likes', ['likeable_type' => ForumComment::class, 'likeable_id' => $comment->id]);
    $this->assertDatabaseMissing('forum_reports', ['reportable_type' => ForumPost::class, 'reportable_id' => $post->id]);
    $this->assertDatabaseMissing('forum_reports', ['reportable_type' => ForumComment::class, 'reportable_id' => $comment->id]);
    expect($topic->fresh()->post_count)->toBe(0);
});
