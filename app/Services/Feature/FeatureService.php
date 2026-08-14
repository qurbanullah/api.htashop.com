<?php

namespace App\Services\Feature;

use App\Helpers\CacheHelper;
use App\Actions\Features\AttachFeaturesAction;
use App\Actions\Features\CreateFeatureAction;
use App\Actions\Features\DetachFeaturesAction;
use App\Actions\Features\GetFeaturesAction;
use App\Actions\Features\GetPopularFeaturesAction;
use App\Actions\Features\SyncFeaturesAction;
use App\Models\Feature;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
class FeatureService
{
    public function __construct(
        protected AttachFeaturesAction $attachFeaturesAction,
        protected DetachFeaturesAction $detachFeaturesAction,
        protected SyncFeaturesAction $syncFeaturesAction,
        protected GetFeaturesAction $getFeaturesAction,
        protected GetPopularFeaturesAction $getPopularFeaturesAction,
        protected CreateFeatureAction $createFeatureAction
    ) {}

    /**
     * Attach features to a featurable model
     */
    public function attachFeatures(Model $featurable, array|Collection $features, string $type = 'product'): Collection
    {
        $result = $this->attachFeaturesAction->execute($featurable, $features, $type);
        $this->clearFeaturableCache($featurable);
        CacheHelper::clearTags(['popular_features', "popular_features:{$type}"]);
        return $result;
    }

    /**
     * Detach features from a featurable model
     */
    public function detachFeatures(Model $featurable, ?array $featureIds = null): void
    {
        $this->detachFeaturesAction->execute($featurable, $featureIds);
        $this->clearFeaturableCache($featurable);
    }

    /**
     * Sync features for a featurable model
     */
    public function syncFeatures(Model $featurable, array|Collection $features, string $type = 'product'): Collection
    {
        $result = $this->syncFeaturesAction->execute($featurable, $features, $type);
        $this->clearFeaturableCache($featurable);
        CacheHelper::clearTags(['popular_features', "popular_features:{$type}"]);
        return $result;
    }

    /**
     * Get features for a featurable model
     */
    public function getFeatures(Model $featurable): Collection
    {
        $cacheKey = $this->getCachePrefix($featurable) . ':features';
        $prefix = $this->getCachePrefix($featurable);

        return CacheHelper::remember([$prefix], $cacheKey, now()->addMinutes(60), function () use ($featurable) {
            return $this->getFeaturesAction->execute($featurable);
        });
    }

    /**
     * Get popular features
     */
    public function getPopularFeatures(string $type = 'product', int $limit = 20): Collection
    {
        $cacheKey = "popular_features:{$type}:{$limit}";

        return CacheHelper::remember(['popular_features', "popular_features:{$type}"], 
            $cacheKey,
            now()->addHours(6),
            function () use ($type, $limit) {
                return $this->getPopularFeaturesAction->execute($type, $limit);
            }
        );
    }

    /**
     * Create a new feature
     */
    public function createFeature(array $data): Feature
    {
        $feature = $this->createFeatureAction->execute($data);

        $type = data_get($data, 'type', 'product');
        CacheHelper::clearTags(['popular_features', "popular_features:{$type}"]);

        return $feature;
    }

    /**
     * Find feature by slug and type
     */
    public function findBySlug(string $slug, string $type = 'product'): ?Feature
    {
        $cacheKey = "feature:slug:{$type}:{$slug}";

        return CacheHelper::remember(['features'], 
            $cacheKey,
            now()->addHours(12),
            function () use ($slug, $type) {
                return Feature::where('slug', $slug)
                    ->where('type', $type)
                    ->first();
            }
        );
    }

    /**
     * Search features
     */
    public function searchFeatures(string $query, string $type = 'product', int $limit = 20): Collection
    {
        return Feature::where('type', $type)
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('description', 'like', "%{$query}%");
            })
            ->limit($limit)
            ->get();
    }

    /**
     * Delete unused features
     */
    public function deleteUnusedFeatures(string $type = 'product'): int
    {
        $deleted = Feature::where('type', $type)
            ->where('usage_count', 0)
            ->delete();

        CacheHelper::clearTags(['features', 'popular_features', "popular_features:{$type}"]);

        return $deleted;
    }

    /**
     * Clear cache for a specific featurable
     */
    protected function clearFeaturableCache(Model $featurable): void
    {
        $prefix = $this->getCachePrefix($featurable);
        CacheHelper::clearTags([$prefix]);
    }

    /**
     * Get cache prefix for a featurable model
     */
    protected function getCachePrefix(Model $featurable): string
    {
        return 'features:' . class_basename($featurable) . ':' . $featurable->id;
    }
}
