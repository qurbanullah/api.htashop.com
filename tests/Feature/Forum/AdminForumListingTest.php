<?php

use App\Http\Middleware\ApiAuthenticate;
use App\Models\ForumComment;
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

it('allows admins to list and filter forum posts', function () {
    $admin = User::factory()->create(['name' => 'Admin Reviewer']);
    $admin->assignRole('admin');

    $author = User::factory()->create(['name' => 'Alice Author']);
    $otherAuthor = User::factory()->create(['name' => 'Bob Author']);

    $targetTopic = ForumTopic::create([
        'name' => 'Target Topic',
        'is_active' => true,
        'is_locked' => false,
    ]);

    $otherTopic = ForumTopic::create([
        'name' => 'Other Topic',
        'is_active' => true,
        'is_locked' => false,
    ]);

    $matchingPost = ForumPost::create([
        'user_id' => $author->id,
        'topic_id' => $targetTopic->id,
        'title' => 'Target segmentation workflow',
        'body' => 'This published pinned post should match all applied filters.',
        'status' => 'published',
        'is_pinned' => true,
        'is_featured' => true,
    ]);

    ForumPost::create([
        'user_id' => $author->id,
        'topic_id' => $targetTopic->id,
        'title' => 'Pending topic draft',
        'body' => 'This should be excluded by the status filter.',
        'status' => 'pending',
        'is_pinned' => true,
    ]);

    ForumPost::create([
        'user_id' => $otherAuthor->id,
        'topic_id' => $otherTopic->id,
        'title' => 'Published but unrelated',
        'body' => 'This should be excluded by user, topic, and search filters.',
        'status' => 'published',
        'is_pinned' => true,
    ]);

    $this->actingAs($admin, 'api');

    $response = $this->getJson('/api/v1/admin/forum/posts?status=published&topic_id=' . $targetTopic->id . '&user_id=' . $author->id . '&search=segmentation&is_pinned=1');

    $response->assertOk()->assertJson([
        'success' => true,
        'data' => [
            'total' => 1,
        ],
    ]);

    $posts = $response->json('data.data');

    expect($posts)->toHaveCount(1);
    expect($posts[0]['id'])->toBe($matchingPost->id);
    expect($posts[0]['title'])->toBe('Target segmentation workflow');
    expect($posts[0]['topic']['id'])->toBe($targetTopic->id);
    expect($posts[0]['author']['id'])->toBe($author->id);
});

it('allows admins to list and filter forum comments', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $author = User::factory()->create(['name' => 'Comment Author']);
    $otherUser = User::factory()->create(['name' => 'Another User']);

    $topic = ForumTopic::create([
        'name' => 'Comment Topic',
        'is_active' => true,
        'is_locked' => false,
    ]);

    $post = ForumPost::create([
        'user_id' => $author->id,
        'topic_id' => $topic->id,
        'title' => 'Post with comments',
        'body' => 'This post is used for admin comment filters.',
        'status' => 'published',
    ]);

    $matchingComment = ForumComment::create([
        'user_id' => $author->id,
        'post_id' => $post->id,
        'body' => 'Need moderation on this matching comment.',
        'status' => 'visible',
    ]);

    ForumComment::create([
        'user_id' => $otherUser->id,
        'post_id' => $post->id,
        'body' => 'This hidden comment should be excluded.',
        'status' => 'hidden',
    ]);

    $this->actingAs($admin, 'api');

    $response = $this->getJson('/api/v1/admin/forum/comments?status=visible&post_id=' . $post->id . '&user_id=' . $author->id . '&search=matching');

    $response->assertOk()->assertJson([
        'success' => true,
        'data' => [
            'total' => 1,
        ],
    ]);

    $comments = $response->json('data.data');

    expect($comments)->toHaveCount(1);
    expect($comments[0]['id'])->toBe($matchingComment->id);
    expect($comments[0]['post']['id'])->toBe($post->id);
    expect($comments[0]['author']['id'])->toBe($author->id);
});

it('allows admins to list and filter forum reports', function () {
    $admin = User::factory()->create(['name' => 'Admin Moderator']);
    $admin->assignRole('admin');

    $reporter = User::factory()->create(['name' => 'Reporter']);
    $author = User::factory()->create(['name' => 'Reported Author']);

    $topic = ForumTopic::create([
        'name' => 'Reports Topic',
        'is_active' => true,
        'is_locked' => false,
    ]);

    $post = ForumPost::create([
        'user_id' => $author->id,
        'topic_id' => $topic->id,
        'title' => 'Reported post',
        'body' => 'This post receives a report for listing tests.',
        'status' => 'published',
    ]);

    $comment = ForumComment::create([
        'user_id' => $author->id,
        'post_id' => $post->id,
        'body' => 'Comment that receives a report.',
        'status' => 'visible',
    ]);

    $matchingReport = ForumReport::create([
        'user_id' => $reporter->id,
        'reportable_id' => $post->id,
        'reportable_type' => ForumPost::class,
        'reason' => 'spam',
        'description' => 'This is the matching post report.',
        'status' => 'pending',
    ]);

    ForumReport::create([
        'user_id' => $reporter->id,
        'reportable_id' => $comment->id,
        'reportable_type' => ForumComment::class,
        'reason' => 'other',
        'description' => 'This comment report should be excluded.',
        'status' => 'dismissed',
        'reviewed_by' => $admin->id,
        'reviewed_at' => now(),
    ]);

    $this->actingAs($admin, 'api');

    $response = $this->getJson('/api/v1/admin/forum/reports?status=pending&reason=spam&reportable_type=post');

    $response->assertOk()->assertJson([
        'success' => true,
        'data' => [
            'total' => 1,
        ],
    ]);

    $reports = $response->json('data.data');

    expect($reports)->toHaveCount(1);
    expect($reports[0]['id'])->toBe($matchingReport->id);
    expect($reports[0]['reportable_type'])->toBe('post');
    expect($reports[0]['reason'])->toBe('spam');
    expect($reports[0]['reporter']['id'])->toBe($reporter->id);
});

it('prevents non admins from accessing forum moderation listings', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'api');

    $postsResponse = $this->getJson('/api/v1/admin/forum/posts');
    $commentsResponse = $this->getJson('/api/v1/admin/forum/comments');
    $reportsResponse = $this->getJson('/api/v1/admin/forum/reports');

    $postsResponse->assertStatus(403);
    $commentsResponse->assertStatus(403);
    $reportsResponse->assertStatus(403);
});
