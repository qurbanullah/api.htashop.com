<?php

namespace App\Services\Post;

use App\Helpers\CacheHelper;
use App\Models\Post;
use App\Actions\Post\CreatePostAction;
use App\Actions\Post\UpdatePostAction;
use App\Actions\Post\DeletePostAction;
use App\Actions\Post\SendPostAction;
use App\Actions\Post\SchedulePostAction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Carbon\Carbon;

class PostService
{
    /**
     * Cache tag shared by every post cache entry, enabling efficient
     * tag-based invalidation on Redis/memcached.
     */
    protected const CACHE_TAGS = ['post'];

    protected $createAction;
    protected $updateAction;
    protected $deleteAction;
    protected $sendAction;
    protected $scheduleAction;

    public function __construct(
        CreatePostAction $createAction,
        UpdatePostAction $updateAction,
        DeletePostAction $deleteAction,
        SendPostAction $sendAction,
        SchedulePostAction $scheduleAction
    ) {
        $this->createAction = $createAction;
        $this->updateAction = $updateAction;
        $this->deleteAction = $deleteAction;
        $this->sendAction = $sendAction;
        $this->scheduleAction = $scheduleAction;
    }

    /**
     * Admin listing with optional filters (status, type, search, date range).
     */
    public function getAllPost(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $cacheKey = 'post_list_' . md5(serialize($filters)) . '_page_' . request('page', 1) . '_per_page_' . $perPage;

        return CacheHelper::remember(self::CACHE_TAGS, $cacheKey, 300, function () use ($filters, $perPage) {
            $query = Post::with('creator')->latest();

            if (isset($filters['status'])) {
                $query->where('status', $filters['status']);
            }

            if (isset($filters['type'])) {
                $query->ofType($filters['type']);
            }

            if (isset($filters['search'])) {
                $query->where(function ($q) use ($filters) {
                    $q->where('title', 'like', '%' . $filters['search'] . '%')
                      ->orWhere('excerpt', 'like', '%' . $filters['search'] . '%');
                });
            }

            if (isset($filters['date_from'])) {
                $query->whereDate('created_at', '>=', $filters['date_from']);
            }

            if (isset($filters['date_to'])) {
                $query->whereDate('created_at', '<=', $filters['date_to']);
            }

            return $query->paginate($perPage);
        });
    }

    /**
     * Public post listing (published/sent) with all supported filters.
     *
     * Filters:
     * - search (string)              title/excerpt contains
     * - start_date / end_date        created_at window
     * - tags (string|array)          comma-separated tag names
     * - categories (string|array)    comma-separated category names
     * - latest (bool)                order by created_at desc
     * - type (string)                post type (blog, news, event, ...)
     * - category_slug (string)       primary category slug
     * - primary_category_id (int)    primary category id
     *
     * @return LengthAwarePaginator|Collection
     */
    public function getPublicPosts(array $filters = [], int $perPage = 15, ?int $limit = null): LengthAwarePaginator|Collection
    {
        $cacheKey = 'post_public_list_' . md5(serialize($filters)) . '_page_' . request('page', 1) . '_per_page_' . $perPage . '_limit_' . ($limit ?? 'none');

        return CacheHelper::remember(self::CACHE_TAGS, $cacheKey, 600, function () use ($filters, $perPage, $limit) {
            $query = Post::query()
                ->with(['categories', 'tags', 'primaryCategory'])
                ->whereIn('status', ['published', 'sent'])
                ->when(!empty($filters['latest']), function ($q) {
                    $q->orderByDesc('created_at');
                });

            if (!empty($filters['search'])) {
                $term = trim((string) $filters['search']);
                $query->where(function ($q) use ($term) {
                    $q->where('title', 'like', '%' . $term . '%')
                      ->orWhere('excerpt', 'like', '%' . $term . '%');
                });
            }

            if (!empty($filters['start_date'])) {
                $query->where('created_at', '>=', $filters['start_date']);
            }

            if (!empty($filters['end_date'])) {
                $query->where('created_at', '<=', $filters['end_date']);
            }

            if (!empty($filters['tags'])) {
                $tags = $this->explodeList($filters['tags']);
                $query->whereHas('tags', function ($q) use ($tags) {
                    $q->whereIn('name', $tags);
                });
            }

            if (!empty($filters['categories'])) {
                $categories = $this->explodeList($filters['categories']);
                $query->whereHas('categories', function ($q) use ($categories) {
                    $q->whereIn('name', $categories);
                });
            }

            if (!empty($filters['type'])) {
                $query->ofType($filters['type']);
            }

            if (!empty($filters['category_slug'])) {
                $query->whereHas('primaryCategory', function ($q) use ($filters) {
                    $q->where('slug', $filters['category_slug']);
                });
            }

            if (!empty($filters['primary_category_id'])) {
                $query->where('primary_category_id', $filters['primary_category_id']);
            }

            if ($limit !== null) {
                return $query->limit($limit)->get();
            }

            return $query->paginate($perPage);
        });
    }

    /**
     * Resolve a single public post (published/sent) by id, uuid, or slug.
     */
    public function getPublicPostByIdentifier(string $identifier): ?Post
    {
        $cacheKey = 'post_public_identifier_' . md5($identifier);

        return CacheHelper::remember(self::CACHE_TAGS, $cacheKey, 600, function () use ($identifier) {
            return Post::whereIn('status', ['published', 'sent'])
                ->with(['categories', 'tags', 'primaryCategory'])
                ->where(function ($q) use ($identifier) {
                    $q->where('id', $identifier)
                      ->orWhere('uuid', $identifier)
                      ->orWhere('slug', $identifier);
                })
                ->first();
        });
    }

    /**
     * Single post by uuid (admin — any status).
     */
    public function getPostByUuid(string $uuid): ?Post
    {
        $cacheKey = 'post_uuid_' . $uuid;

        return CacheHelper::remember(self::CACHE_TAGS, $cacheKey, 3600, function () use ($uuid) {
            return Post::where('uuid', $uuid)
                ->with(['creator', 'tags', 'categories'])
                ->first();
        });
    }

    /**
     * Single published blog post by slug.
     */
    public function getPostBySlug(string $slug): ?Post
    {
        $cacheKey = 'post_slug_' . $slug;

        return CacheHelper::remember(self::CACHE_TAGS, $cacheKey, 3600, function () use ($slug) {
            return Post::where('slug', $slug)
                ->where('is_published_as_blog', true)
                ->where('status', 'published')
                ->with(['creator', 'tags', 'categories'])
                ->first();
        });
    }

    public function createPost(array $data, ?int $createdBy = null): Post
    {
        // Handle authentication - use provided user ID or current auth user
        if (!isset($data['created_by'])) {
            $data['created_by'] = $createdBy ?? auth()->id();
        }

        $post = $this->createAction->handle($data);
        $this->clearPostCache();
        return $post;
    }

    public function updatePost(Post $post, array $data): Post
    {
        $updatedPost = $this->updateAction->handle($post, $data);
        $this->clearPostCache($post);
        return $updatedPost;
    }

    public function deletePost(Post $post): bool
    {
        $result = $this->deleteAction->execute($post);
        $this->clearPostCache($post);
        return $result;
    }

    public function sendPost(Post $post, array $options = []): array
    {
        $result = $this->sendAction->execute($post, $options);
        $this->clearPostCache($post);
        return $result;
    }

    public function schedulePost(Post $post, Carbon $scheduledAt): Post
    {
        $updatedPost = $this->scheduleAction->execute($post, $scheduledAt);
        $this->clearPostCache($post);
        return $updatedPost;
    }

    public function publishAsBlog(Post $post, bool $publish = true): Post
    {
        $post->update([
            'is_published_as_blog' => $publish,
            'status' => $publish ? 'published' : 'draft'
        ]);

        $this->clearPostCache($post);
        return $post->fresh();
    }

    /**
     * @deprecated Use publishAsBlog() instead. Left for backward compatibility.
     */
    public function publishAsBlolg(Post $post, bool $publish = true): Post
    {
        return $this->publishAsBlog($post, $publish);
    }

    public function getScheduledPost(): Collection
    {
        return CacheHelper::remember(self::CACHE_TAGS, 'post_scheduled', 60, function () {
            return Post::readyToSend()->get();
        });
    }

    public function getPostStats(): array
    {
        return CacheHelper::remember(self::CACHE_TAGS, 'post_stats', 300, function () {
            return [
                'total' => Post::count(),
                'drafts' => Post::draft()->count(),
                'scheduled' => Post::scheduled()->count(),
                'published' => Post::published()->count(),
                'sent' => Post::sent()->count(),
                'total_sent_count' => Post::sum('sent_count'),
                'total_opened_count' => Post::sum('opened_count'),
                'total_clicked_count' => Post::sum('clicked_count'),
                'average_open_rate' => $this->calculateAverageOpenRate(),
                'average_click_rate' => $this->calculateAverageClickRate(),
            ];
        });
    }

    public function searchPost(string $query, int $perPage = 10): LengthAwarePaginator
    {
        $cacheKey = 'post_search_' . md5($query) . '_page_' . request('page', 1) . '_per_page_' . $perPage;

        return CacheHelper::remember(self::CACHE_TAGS, $cacheKey, 300, function () use ($query, $perPage) {
            return Post::where('title', 'like', '%' . $query . '%')
                ->orWhere('excerpt', 'like', '%' . $query . '%')
                ->orWhere('content', 'like', '%' . $query . '%')
                ->with('creator')
                ->latest()
                ->paginate($perPage);
        });
    }

    /**
     * Get publishing trends for the last N days
     */
    public function getPublishingTrends(int $days = 30): array
    {
        $cacheKey = 'post_publishing_trends_' . $days;

        return CacheHelper::remember(self::CACHE_TAGS, $cacheKey, 3600, function () use ($days) {
            $endDate = now()->endOfDay();
            $startDate = now()->subDays($days - 1)->startOfDay();

            $trends = [];

            for ($i = 0; $i < $days; $i++) {
                $date = $startDate->copy()->addDays($i);
                $count = Post::whereDate('created_at', $date->toDateString())->count();

                $trends[] = [
                    'date' => $date->format('M j'), // Format like "Jan 8", "Feb 5"
                    'post' => $count,
                    'full_date' => $date->toDateString(),
                ];
            }

            return $trends;
        });
    }

    /**
     * Invalidate the post cache. On Redis/memcached this is a single
     * tag-based flush; the explicit forgets also cover drivers without
     * tag support.
     */
    protected function clearPostCache(?Post $post = null): void
    {
        CacheHelper::clearTags(self::CACHE_TAGS);

        CacheHelper::forget(self::CACHE_TAGS, 'post_stats');
        CacheHelper::forget(self::CACHE_TAGS, 'post_scheduled');
        CacheHelper::forget(self::CACHE_TAGS, 'post_publishing_trends_30');

        if ($post) {
            CacheHelper::forget(self::CACHE_TAGS, 'post_uuid_' . $post->uuid);
            CacheHelper::forget(self::CACHE_TAGS, 'post_slug_' . $post->slug);
            CacheHelper::forget(self::CACHE_TAGS, 'post_public_identifier_' . md5((string) $post->id));
            CacheHelper::forget(self::CACHE_TAGS, 'post_public_identifier_' . md5($post->uuid));
            CacheHelper::forget(self::CACHE_TAGS, 'post_public_identifier_' . md5($post->slug));
        }
    }

    /**
     * Normalize a comma-separated string or array into a unique list.
     */
    protected function explodeList(array|string $value): array
    {
        $items = is_array($value) ? $value : explode(',', $value);

        return array_values(array_unique(array_filter(array_map('trim', $items))));
    }

    protected function calculateAverageOpenRate(): float
    {
        $post = Post::sent()->where('sent_count', '>', 0)->get();

        if ($post->isEmpty()) {
            return 0.0;
        }

        $totalRate = $post->sum(function ($post) {
            return $post->open_rate;
        });

        return round($totalRate / $post->count(), 2);
    }

    protected function calculateAverageClickRate(): float
    {
        $post = Post::sent()->where('opened_count', '>', 0)->get();

        if ($post->isEmpty()) {
            return 0.0;
        }

        $totalRate = $post->sum(function ($post) {
            return $post->click_rate;
        });

        return round($totalRate / $post->count(), 2);
    }
}
