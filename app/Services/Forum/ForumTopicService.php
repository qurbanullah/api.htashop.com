<?php

namespace App\Services\Forum;

use App\Helpers\CacheHelper;
use App\Enums\ForumPostStatusEnum;
use App\Models\ForumComment;
use App\Models\ForumPost;
use App\Models\ForumTopic;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ForumTopicService
{
    private const CACHE_PREFIX = 'forum_topics';
    private const CACHE_TTL = 600; // 10 minutes

    public function getActiveTopics(): \Illuminate\Database\Eloquent\Collection
    {
        return CacheHelper::remember([], self::CACHE_PREFIX . ':active', self::CACHE_TTL, function () {
            return ForumTopic::active()
                ->ordered()
                ->withCount(['publishedPosts'])
                ->get();
        });
    }

    public function getAllTopics(array $filters = []): LengthAwarePaginator
    {
        $query = ForumTopic::query()
            ->with('creator:id,name,email')
            ->withCount(['posts', 'publishedPosts'])
            ->ordered();

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        $perPage = $filters['per_page'] ?? 20;

        return $query->paginate($perPage);
    }

    public function findBySlug(string $slug): ?ForumTopic
    {
        return ForumTopic::active()
            ->where('slug', $slug)
            ->withCount(['publishedPosts'])
            ->first();
    }

    public function findByUuid(string $uuid): ?ForumTopic
    {
        return ForumTopic::where('uuid', $uuid)
            ->withCount(['posts', 'publishedPosts'])
            ->with('creator:id,name,email')
            ->first();
    }

    public function create(array $data): ForumTopic
    {
        $topic = DB::transaction(function () use ($data) {
            return ForumTopic::create($data);
        });

        $this->clearCache();
        return $topic;
    }

    public function update(ForumTopic $topic, array $data): ForumTopic
    {
        DB::transaction(function () use ($topic, $data) {
            $topic->update($data);
        });

        $this->clearCache();
        return $topic->fresh();
    }

    public function delete(ForumTopic $topic): void
    {
        DB::transaction(function () use ($topic) {
            $topic->posts()
                ->with(['comments.likes', 'comments.reports', 'likes', 'reports'])
                ->get()
                ->each(function (ForumPost $post) {
                    $post->likes()->delete();
                    $post->reports()->delete();

                    $post->comments->each(function (ForumComment $comment) {
                        $comment->likes()->delete();
                        $comment->reports()->delete();
                        $comment->delete();
                    });

                    $post->delete();
                });

            $topic->delete();
        });

        $this->clearCache();
    }

    public function reorder(array $orderedIds): void
    {
        DB::transaction(function () use ($orderedIds) {
            foreach ($orderedIds as $index => $id) {
                ForumTopic::where('id', $id)->update(['sort_order' => $index]);
            }
        });

        $this->clearCache();
    }

    public function refreshPostCount(ForumTopic $topic): void
    {
        $topic->update([
            'post_count' => $topic->posts()
                ->where('status', ForumPostStatusEnum::PUBLISHED->value)
                ->count(),
        ]);

        $this->clearCache();
    }

    private function clearCache(): void
    {
        CacheHelper::forget([], self::CACHE_PREFIX . ':active');
    }
}
