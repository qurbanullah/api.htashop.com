<?php

namespace App\Services\Tag;

use App\Helpers\CacheHelper;
use App\Models\Tag;
use App\Actions\Tags\AttachTagsAction;
use App\Actions\Tags\DetachTagsAction;
use App\Actions\Tags\SyncTagsAction;
use App\Actions\Tags\GetTagsAction;
use App\Actions\Tags\GetPopularTagsAction;
use App\Actions\Tags\CreateTagAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
class TagService
{
    public function __construct(
        private AttachTagsAction $attachTagsAction,
        private DetachTagsAction $detachTagsAction,
        private SyncTagsAction $syncTagsAction,
        private GetTagsAction $getTagsAction,
        private GetPopularTagsAction $getPopularTagsAction,
        private CreateTagAction $createTagAction,
    ) {}

    /**
     * Get cache prefix for a taggable model
     */
    private function getCachePrefix(Model $taggable): string
    {
        return 'tags:' . get_class($taggable) . ':' . $taggable->id;
    }

    /**
     * Clear cache for a taggable model
     */
    private function clearTaggableCache(Model $taggable): void
    {
        $prefix = $this->getCachePrefix($taggable);
        CacheHelper::clearTags([$prefix]);
    }

    /**
     * Attach tags to a model
     *
     * @param Model $taggable The model to attach tags to
     * @param array|Collection $tags Tag names or IDs
     * @param string $type Tag type (product, blog, etc.)
     * @return Collection The attached tags
     */
    public function attachTags(Model $taggable, array|Collection $tags, string $type = 'product'): Collection
    {
        $result = $this->attachTagsAction->execute($taggable, $tags, $type);

        // Clear cache
        $this->clearTaggableCache($taggable);
        CacheHelper::clearTags(['popular_tags', "popular_tags:{$type}"]);

        return $result;
    }

    /**
     * Detach tags from a model
     *
     * @param Model $taggable The model to detach tags from
     * @param array|Collection|null $tags Tag IDs to detach (null = detach all)
     * @return void
     */
    public function detachTags(Model $taggable, array|Collection|null $tags = null): void
    {
        $this->detachTagsAction->execute($taggable, $tags);

        // Clear cache
        $this->clearTaggableCache($taggable);
        CacheHelper::clearTags(['popular_tags']);
    }

    /**
     * Sync tags for a model (replace all tags)
     *
     * @param Model $taggable The model to sync tags for
     * @param array|Collection $tags Tag names or IDs
     * @param string $type Tag type (product, blog, etc.)
     * @return Collection The synced tags
     */
    public function syncTags(Model $taggable, array|Collection $tags, string $type = 'product'): Collection
    {
        $result = $this->syncTagsAction->execute($taggable, $tags, $type);

        // Clear cache
        $this->clearTaggableCache($taggable);
        CacheHelper::clearTags(['popular_tags', "popular_tags:{$type}"]);

        return $result;
    }

    /**
     * Get tags for a model (with caching)
     *
     * @param Model $taggable The model to get tags for
     * @return Collection
     */
    public function getTags(Model $taggable): Collection
    {
        $cacheKey = $this->getCachePrefix($taggable) . ':tags';
        $prefix = $this->getCachePrefix($taggable);

        return CacheHelper::remember([$prefix], $cacheKey, now()->addMinutes(60), function () use ($taggable) {
            return $this->getTagsAction->execute($taggable);
        });
    }

    /**
     * Get popular tags by type (with caching)
     *
     * @param string $type Tag type (product, blog, etc.)
     * @param int $limit Number of tags to return
     * @return Collection
     */
    public function getPopularTags(string $type = 'product', int $limit = 20): Collection
    {
        $cacheKey = "popular_tags:{$type}:{$limit}";

        return CacheHelper::remember(['popular_tags', "popular_tags:{$type}"], 
            $cacheKey,
            now()->addHours(6),
            function () use ($type, $limit) {
                return $this->getPopularTagsAction->execute($type, $limit);
            }
        );
    }

    /**
     * Create a new tag
     *
     * @param array $data Tag data [name, type, slug]
     * @return Tag
     */
    public function createTag(array $data): Tag
    {
        $tag = $this->createTagAction->execute($data);

        // Clear popular tags cache
        $type = $data['type'] ?? 'product';
        CacheHelper::clearTags(['popular_tags', "popular_tags:{$type}"]);

        return $tag;
    }

    /**
     * Find tag by slug
     *
     * @param string $slug
     * @param string $type
     * @return Tag|null
     */
    public function findBySlug(string $slug, string $type = 'product'): ?Tag
    {
        $cacheKey = "tag:{$type}:{$slug}";

        return CacheHelper::remember([], $cacheKey, now()->addHours(12), function () use ($slug, $type) {
            return Tag::where('slug', $slug)
                ->where('type', $type)
                ->first();
        });
    }

    /**
     * Get all items tagged with a specific tag
     *
     * @param Tag $tag
     * @param string $modelClass The model class to get (Product::class, Post::class, etc.)
     * @return Collection
     */
    public function getTaggedItems(Tag $tag, string $modelClass): Collection
    {
        $cacheKey = "tag:{$tag->id}:items:" . class_basename($modelClass);

        return CacheHelper::remember(["tag:{$tag->id}"], 
            $cacheKey,
            now()->addMinutes(30),
            function () use ($tag, $modelClass) {
                return $tag->$modelClass()->get();
            }
        );
    }

    /**
     * Search tags by name
     *
     * @param string $search
     * @param string $type
     * @param int $limit
     * @return Collection
     */
    public function searchTags(string $search, string $type = 'product', int $limit = 10): Collection
    {
        return Tag::where('type', $type)
            ->where('name', 'like', "%{$search}%")
            ->orderBy('usage_count', 'desc')
            ->orderBy('name')
            ->limit($limit)
            ->get();
    }

    /**
     * Clean up unused tags (usage_count = 0)
     *
     * @param string|null $type Specific type or null for all
     * @return int Number of tags deleted
     */
    public function cleanupUnusedTags(string $type = null): int
    {
        $query = Tag::where('usage_count', 0);

        if ($type) {
            $query->where('type', $type);
        }

        $count = $query->count();
        $query->delete();

        // Clear cache
        CacheHelper::clearTags(['popular_tags']);

        return $count;
    }
}
