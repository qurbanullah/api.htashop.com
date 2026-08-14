<?php

namespace App\Services\Forum;

use App\Enums\ForumPostStatusEnum;
use App\Models\ForumComment;
use App\Models\ForumPost;
use App\Models\ForumTopic;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ForumPostService
{
    public function __construct(
        protected ForumTopicService $topicService,
    ) {}

    public function getPublicPosts(array $filters = []): LengthAwarePaginator
    {
        $query = ForumPost::published()
            ->with([
                'author:id,name,first_name,last_name',
                'topic:id,name,slug,color,icon',
            ])
            ->withCount(['visibleComments']);

        if (!empty($filters['topic_id'])) {
            $query->byTopic($filters['topic_id']);
        }

        if (!empty($filters['topic_slug'])) {
            $topic = ForumTopic::where('slug', $filters['topic_slug'])->first();
            if ($topic) {
                $query->byTopic($topic->id);
            }
        }

        if (!empty($filters['search'])) {
            $query->search($filters['search']);
        }

        if (!empty($filters['featured'])) {
            $query->where('is_featured', true);
        }

        $sort = $filters['sort'] ?? 'latest';
        $query = match ($sort) {
            'popular', 'most_liked' => $query->orderByDesc('like_count'),
            'most_commented' => $query->orderByDesc('comment_count'),
            'most_viewed' => $query->orderByDesc('view_count'),
            default => $query->orderByDesc('is_pinned')->orderByDesc('created_at'),
        };

        $perPage = min($filters['per_page'] ?? 15, 50);

        return $query->paginate($perPage);
    }

    public function getAllPostsAdmin(array $filters = []): LengthAwarePaginator
    {
        $query = ForumPost::query()
            ->with([
                'author:id,name,email',
                'topic:id,name,slug',
            ])
            ->withCount(['comments', 'likes', 'reports']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['topic_id'])) {
            $query->where('topic_id', $filters['topic_id']);
        }

        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (!empty($filters['search'])) {
            $query->search($filters['search']);
        }

        if (!empty($filters['is_pinned'])) {
            $query->where('is_pinned', true);
        }

        if (!empty($filters['is_locked'])) {
            $query->where('is_locked', true);
        }

        if (!empty($filters['is_featured'])) {
            $query->where('is_featured', true);
        }

        $query->orderByDesc('created_at');

        $perPage = min($filters['per_page'] ?? 15, 50);

        return $query->paginate($perPage);
    }

    public function getUserPosts(int $userId, array $filters = []): LengthAwarePaginator
    {
        $query = ForumPost::where('user_id', $userId)
            ->with(['topic:id,name,slug,color,icon'])
            ->withCount(['visibleComments', 'likes']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        } else {
            $query->whereIn('status', [
                ForumPostStatusEnum::PUBLISHED->value,
                ForumPostStatusEnum::PENDING->value,
            ]);
        }

        $query->orderByDesc('created_at');

        $perPage = min($filters['per_page'] ?? 15, 50);

        return $query->paginate($perPage);
    }

    public function findBySlug(string $slug): ?ForumPost
    {
        return ForumPost::where('slug', $slug)
            ->with([
                'author:id,name,first_name,last_name',
                'topic:id,name,slug,color,icon',
            ])
            ->withCount(['visibleComments', 'likes'])
            ->first();
    }

    public function findByUuid(string $uuid): ?ForumPost
    {
        return ForumPost::where('uuid', $uuid)
            ->with([
                'author:id,name,email',
                'topic:id,name,slug',
            ])
            ->withCount(['comments', 'likes', 'reports'])
            ->first();
    }

    public function create(array $data, User $user): ForumPost
    {
        $post = DB::transaction(function () use ($data, $user) {
            $data['user_id'] = $user->id;
            $data['status'] = ForumPostStatusEnum::PUBLISHED->value;

            $post = ForumPost::create($data);

            return $post;
        });

        $this->topicService->refreshPostCount($post->topic);

        return $post->load(['author:id,name,first_name,last_name', 'topic:id,name,slug,color,icon']);
    }

    public function update(ForumPost $post, array $data): ForumPost
    {
        $originalTopicId = $post->topic_id;
        DB::transaction(function () use ($post, $data) {
            $post->update($data);
        });

        if ($originalTopicId !== $post->topic_id) {
            $originalTopic = ForumTopic::find($originalTopicId);
            if ($originalTopic) {
                $this->topicService->refreshPostCount($originalTopic);
            }
        }

        if ($post->topic) {
            $this->topicService->refreshPostCount($post->topic);
        }

        return $post->fresh([
            'author:id,name,first_name,last_name',
            'topic:id,name,slug,color,icon',
        ]);
    }

    public function delete(ForumPost $post): void
    {
        DB::transaction(function () use ($post) {
            $topicId = $post->topic_id;
            $this->deletePostRelations($post);
            $post->delete();

            $topic = ForumTopic::find($topicId);
            if ($topic) {
                $this->topicService->refreshPostCount($topic);
            }
        });
    }

    public function toggleLike(ForumPost $post, User $user): array
    {
        $existing = $post->likes()->where('user_id', $user->id)->first();

        if ($existing) {
            $existing->delete();
            $post->refreshLikeCount();
            return ['liked' => false, 'like_count' => $post->fresh()->like_count];
        }

        $post->likes()->create(['user_id' => $user->id]);
        $post->refreshLikeCount();
        return ['liked' => true, 'like_count' => $post->fresh()->like_count];
    }

    public function incrementViews(ForumPost $post): void
    {
        $post->incrementViewCount();
    }

    public function getStats(): array
    {
        return [
            'total_posts' => ForumPost::count(),
            'published_posts' => ForumPost::published()->count(),
            'pending_posts' => ForumPost::where('status', ForumPostStatusEnum::PENDING->value)->count(),
            'hidden_posts' => ForumPost::where('status', ForumPostStatusEnum::HIDDEN->value)->count(),
            'pinned_posts' => ForumPost::where('is_pinned', true)->count(),
            'featured_posts' => ForumPost::where('is_featured', true)->count(),
            'total_views' => ForumPost::sum('view_count'),
            'total_likes' => ForumPost::sum('like_count'),
        ];
    }

    private function deletePostRelations(ForumPost $post): void
    {
        $post->likes()->delete();
        $post->reports()->delete();

        $post->comments()
            ->with(['likes', 'reports'])
            ->get()
            ->each(function (ForumComment $comment) {
                $comment->likes()->delete();
                $comment->reports()->delete();
                $comment->delete();
            });
    }
}
