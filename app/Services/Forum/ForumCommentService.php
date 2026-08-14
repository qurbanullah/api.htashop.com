<?php

namespace App\Services\Forum;

use App\Enums\ForumCommentStatusEnum;
use App\Models\ForumComment;
use App\Models\ForumPost;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ForumCommentService
{
    public function getPostComments(ForumPost $post, array $filters = []): LengthAwarePaginator
    {
        $query = ForumComment::where('post_id', $post->id)
            ->where('status', ForumCommentStatusEnum::VISIBLE->value)
            ->whereNull('parent_id')
            ->with([
                'author:id,name,first_name,last_name',
                'visibleReplies' => function ($q) {
                    $q->with('author:id,name,first_name,last_name')
                      ->orderBy('created_at', 'asc');
                },
            ])
            ->withCount(['visibleReplies', 'likes'])
            ->orderBy('created_at', 'desc');

        $perPage = min($filters['per_page'] ?? 20, 50);

        return $query->paginate($perPage);
    }

    public function getAllCommentsAdmin(array $filters = []): LengthAwarePaginator
    {
        $query = ForumComment::query()
            ->with([
                'author:id,name,email',
                'post:id,uuid,title,slug',
            ])
            ->withCount(['replies', 'likes', 'reports']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['post_id'])) {
            $query->where('post_id', $filters['post_id']);
        }

        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where('body', 'like', "%{$search}%");
        }

        $query->orderByDesc('created_at');

        $perPage = min($filters['per_page'] ?? 15, 50);

        return $query->paginate($perPage);
    }

    public function create(ForumPost $post, User $user, array $data): ForumComment
    {
        $comment = DB::transaction(function () use ($post, $user, $data) {
            return ForumComment::create([
                'user_id' => $user->id,
                'post_id' => $post->id,
                'parent_id' => $data['parent_id'] ?? null,
                'body' => $data['body'],
                'status' => ForumCommentStatusEnum::VISIBLE->value,
            ]);
        });

        return $comment->load('author:id,name,first_name,last_name');
    }

    public function update(ForumComment $comment, array $data): ForumComment
    {
        DB::transaction(function () use ($comment, $data) {
            $comment->update(['body' => $data['body']]);
        });

        return $comment->fresh('author:id,name,first_name,last_name');
    }

    public function delete(ForumComment $comment): void
    {
        DB::transaction(function () use ($comment) {
            $post = $comment->post;
            $comment->delete();
            if ($post) {
                $post->refreshCommentCount();
            }
        });
    }

    public function toggleLike(ForumComment $comment, User $user): array
    {
        $existing = $comment->likes()->where('user_id', $user->id)->first();

        if ($existing) {
            $existing->delete();
            $comment->refreshLikeCount();
            return ['liked' => false, 'like_count' => $comment->fresh()->like_count];
        }

        $comment->likes()->create(['user_id' => $user->id]);
        $comment->refreshLikeCount();
        return ['liked' => true, 'like_count' => $comment->fresh()->like_count];
    }

    public function hideComment(ForumComment $comment): ForumComment
    {
        $comment->update(['status' => ForumCommentStatusEnum::HIDDEN->value]);
        $comment->post->refreshCommentCount();
        return $comment->fresh();
    }

    public function unhideComment(ForumComment $comment): ForumComment
    {
        $comment->update(['status' => ForumCommentStatusEnum::VISIBLE->value]);
        $comment->post->refreshCommentCount();
        return $comment->fresh();
    }
}
