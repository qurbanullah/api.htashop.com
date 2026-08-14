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

it('refreshes topic counters when an admin moves a post between topics', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $sourceTopic = ForumTopic::create([
        'name' => 'Source Topic',
        'is_active' => true,
        'is_locked' => false,
        'post_count' => 0,
    ]);

    $destinationTopic = ForumTopic::create([
        'name' => 'Destination Topic',
        'is_active' => true,
        'is_locked' => false,
        'post_count' => 0,
    ]);

    $post = ForumPost::create([
        'user_id' => $admin->id,
        'topic_id' => $sourceTopic->id,
        'title' => 'Move this post',
        'body' => 'This post should end up under a different topic.',
        'status' => 'published',
    ]);

    $sourceTopic->update(['post_count' => 1]);

    $this->actingAs($admin, 'api');

    $response = $this->putJson("/api/v1/admin/forum/posts/{$post->uuid}", [
        'topic_id' => $destinationTopic->id,
    ]);

    $response->assertOk()->assertJson([
        'success' => true,
        'data' => [
            'id' => $post->id,
        ],
    ]);

    expect($sourceTopic->fresh()->post_count)->toBe(0);
    expect($destinationTopic->fresh()->post_count)->toBe(1);
});

it('removes associated forum content when an admin deletes a topic', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $topic = ForumTopic::create([
        'name' => 'Admin Cleanup Topic',
        'is_active' => true,
        'is_locked' => false,
        'post_count' => 0,
    ]);

    $post = ForumPost::create([
        'user_id' => $admin->id,
        'topic_id' => $topic->id,
        'title' => 'Topic cleanup post',
        'body' => 'Deleting the topic should clean up this entire thread.',
        'status' => 'published',
    ]);

    $comment = ForumComment::create([
        'user_id' => $admin->id,
        'post_id' => $post->id,
        'body' => 'Topic cleanup comment',
        'status' => 'visible',
    ]);

    ForumLike::create([
        'user_id' => $admin->id,
        'likeable_id' => $post->id,
        'likeable_type' => ForumPost::class,
    ]);

    ForumReport::create([
        'user_id' => $admin->id,
        'reportable_id' => $comment->id,
        'reportable_type' => ForumComment::class,
        'reason' => 'spam',
        'status' => 'pending',
    ]);

    $topic->update(['post_count' => 1]);

    $this->actingAs($admin, 'api');

    $response = $this->deleteJson("/api/v1/admin/forum/topics/{$topic->uuid}");

    $response->assertOk()->assertJson(['success' => true]);

    $this->assertSoftDeleted('forum_topics', ['id' => $topic->id]);
    $this->assertSoftDeleted('forum_posts', ['id' => $post->id]);
    $this->assertSoftDeleted('forum_comments', ['id' => $comment->id]);
    $this->assertDatabaseMissing('forum_likes', ['likeable_type' => ForumPost::class, 'likeable_id' => $post->id]);
    $this->assertDatabaseMissing('forum_reports', ['reportable_type' => ForumComment::class, 'reportable_id' => $comment->id]);
});
