<?php

namespace App\Services\Specification;

use App\Helpers\CacheHelper;
use App\Actions\Specifications\AttachSpecificationsAction;
use App\Actions\Specifications\CreateSpecificationAction;
use App\Actions\Specifications\DetachSpecificationsAction;
use App\Actions\Specifications\GetSpecificationsAction;
use App\Actions\Specifications\GetPopularSpecificationsAction;
use App\Actions\Specifications\SyncSpecificationsAction;
use App\Models\Specification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
class SpecificationService
{
    public function __construct(
        protected AttachSpecificationsAction $attachSpecificationsAction,
        protected DetachSpecificationsAction $detachSpecificationsAction,
        protected SyncSpecificationsAction $syncSpecificationsAction,
        protected GetSpecificationsAction $getSpecificationsAction,
        protected GetPopularSpecificationsAction $getPopularSpecificationsAction,
        protected CreateSpecificationAction $createSpecificationAction
    ) {}

    /**
     * Attach specifications to a specifiable model
     */
    public function attachSpecifications(Model $specifiable, array|Collection $specifications, string $type = 'product'): Collection
    {
        $result = $this->attachSpecificationsAction->execute($specifiable, $specifications, $type);
        $this->clearSpecifiableCache($specifiable);
        CacheHelper::clearTags(['popular_specifications', "popular_specifications:{$type}"]);
        return $result;
    }

    /**
     * Detach specifications from a specifiable model
     */
    public function detachSpecifications(Model $specifiable, ?array $specificationIds = null): void
    {
        $this->detachSpecificationsAction->execute($specifiable, $specificationIds);
        $this->clearSpecifiableCache($specifiable);
    }

    /**
     * Sync specifications for a specifiable model
     */
    public function syncSpecifications(Model $specifiable, array|Collection $specifications, string $type = 'product'): Collection
    {
        $result = $this->syncSpecificationsAction->execute($specifiable, $specifications, $type);
        $this->clearSpecifiableCache($specifiable);
        CacheHelper::clearTags(['popular_specifications', "popular_specifications:{$type}"]);
        return $result;
    }

    /**
     * Get specifications for a specifiable model
     */
    public function getSpecifications(Model $specifiable): Collection
    {
        $cacheKey = $this->getCachePrefix($specifiable) . ':specifications';
        $prefix = $this->getCachePrefix($specifiable);

        return CacheHelper::remember([$prefix], $cacheKey, now()->addMinutes(60), function () use ($specifiable) {
            return $this->getSpecificationsAction->execute($specifiable);
        });
    }

    /**
     * Get specifications grouped by group
     */
    public function getGroupedSpecifications(Model $specifiable): Collection
    {
        $specifications = $this->getSpecifications($specifiable);

        return $specifications->groupBy('group')->map(function ($groupSpecs) {
            return $groupSpecs->map(function ($spec) {
                return [
                    'id' => $spec->id,
                    'name' => $spec->name,
                    'value' => $spec->pivot->value,
                    'unit' => $spec->unit,
                ];
            });
        });
    }

    /**
     * Get popular specifications
     */
    public function getPopularSpecifications(string $type = 'product', int $limit = 20): Collection
    {
        $cacheKey = "popular_specifications:{$type}:{$limit}";

        return CacheHelper::remember(['popular_specifications', "popular_specifications:{$type}"], 
            $cacheKey,
            now()->addHours(6),
            function () use ($type, $limit) {
                return $this->getPopularSpecificationsAction->execute($type, $limit);
            }
        );
    }

    /**
     * Create a new specification
     */
    public function createSpecification(array $data): Specification
    {
        $specification = $this->createSpecificationAction->execute($data);

        $type = data_get($data, 'type', 'product');
        CacheHelper::clearTags(['popular_specifications', "popular_specifications:{$type}"]);

        return $specification;
    }

    /**
     * Find specification by slug and type
     */
    public function findBySlug(string $slug, string $type = 'product'): ?Specification
    {
        $cacheKey = "specification:slug:{$type}:{$slug}";

        return CacheHelper::remember(['specifications'], 
            $cacheKey,
            now()->addHours(12),
            function () use ($slug, $type) {
                return Specification::where('slug', $slug)
                    ->where('type', $type)
                    ->first();
            }
        );
    }

    /**
     * Search specifications
     */
    public function searchSpecifications(string $query, string $type = 'product', int $limit = 20): Collection
    {
        return Specification::where('type', $type)
            ->with('munit')
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('description', 'like', "%{$query}%");
            })
            ->limit($limit)
            ->get();
    }

    /**
     * Delete unused specifications
     */
    public function deleteUnusedSpecifications(string $type = 'product'): int
    {
        $deleted = Specification::where('type', $type)
            ->where('usage_count', 0)
            ->delete();

        CacheHelper::clearTags(['specifications', 'popular_specifications', "popular_specifications:{$type}"]);

        return $deleted;
    }

    /**
     * Clear cache for a specific specifiable
     */
    protected function clearSpecifiableCache(Model $specifiable): void
    {
        $prefix = $this->getCachePrefix($specifiable);
        CacheHelper::clearTags([$prefix]);
    }

    /**
     * Get cache prefix for a specifiable model
     */
    protected function getCachePrefix(Model $specifiable): string
    {
        return 'specifications:' . class_basename($specifiable) . ':' . $specifiable->id;
    }
}
