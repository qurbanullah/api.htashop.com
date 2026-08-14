<?php

namespace App\Services\Tutorial;

use App\Helpers\CacheHelper;
use App\Services\Dam\DamService;
use App\Models\Tutorial;
use App\Actions\Tutorial\CreateTutorialAction;
use App\Actions\Tutorial\UpdateTutorialAction;
use App\Actions\Tutorial\DeleteTutorialAction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Pagination\LengthAwarePaginator;
use Carbon\Carbon;

/**
 * Service class for Tutorial business logic.
 *
 * This class follows SOLID principles:
 * - Single Responsibility: Orchestrates tutorial-related business logic
 * - Open/Closed: Extendable without modification
 * - Dependency Inversion: Depends on Action abstractions
 */
class TutorialService
{
    protected $createAction;
    protected $updateAction;
    protected $deleteAction;
    protected $damService;

    public function __construct(
        CreateTutorialAction $createAction,
        UpdateTutorialAction $updateAction,
        DeleteTutorialAction $deleteAction,
        DamService $damService
    ) {
        $this->createAction = $createAction;
        $this->updateAction = $updateAction;
        $this->deleteAction = $deleteAction;
        $this->damService = $damService;
    }

    /**
     * Get all tutorials with filters and pagination
     */
    public function getAllTutorials(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $cacheKey = 'tutorials_' . md5(serialize($filters)) . '_page_' . request('page', 1) . '_per_page_' . $perPage;

        return CacheHelper::remember([], $cacheKey, 300, function () use ($filters, $perPage) {
            $query = Tutorial::with($this->tutorialRelations())->latest();

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

            if (isset($filters['difficulty_level'])) {
                $query->where('difficulty_level', $filters['difficulty_level']);
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
     * Get public (published) tutorials for frontend
     */
    public function getPublicTutorials(int $perPage = 12): LengthAwarePaginator
    {
        $cacheKey = 'public_tutorials_page_' . request('page', 1) . '_per_page_' . $perPage;

        return CacheHelper::remember([], $cacheKey, 600, function () use ($perPage) {
            return Tutorial::published()
                ->with($this->tutorialRelations())
                ->recent()
                ->paginate($perPage);
        });
    }

    /**
     * Get tutorial by UUID
     */
    public function getTutorialByUuid(string $uuid): ?Tutorial
    {
        $cacheKey = 'tutorial_uuid_' . $uuid;

        return CacheHelper::remember([], $cacheKey, 3600, function () use ($uuid) {
            return Tutorial::where('uuid', $uuid)
                ->with($this->tutorialRelations())
                ->first();
        });
    }

    /**
     * Get tutorial by slug (for public access)
     */
    public function getTutorialBySlug(string $slug): ?Tutorial
    {
        $cacheKey = 'tutorial_slug_' . $slug;

        return CacheHelper::remember([], $cacheKey, 3600, function () use ($slug) {
            return Tutorial::where('slug', $slug)
                ->published()
                ->with($this->tutorialRelations())
                ->first();
        });
    }

    /**
     * Create a new tutorial
     */
    public function createTutorial(array $data, ?int $createdBy = null): Tutorial
    {
        if (!isset($data['created_by'])) {
            $data['created_by'] = $createdBy ?? Auth::id();
        }

        $tutorial = $this->createAction->handle($data);
        $this->syncDamAssets($tutorial, $data);
        $this->clearTutorialCache();

        return $this->loadCurrentDamRelations($tutorial);
    }

    /**
     * Update an existing tutorial
     */
    public function updateTutorial(Tutorial $tutorial, array $data): Tutorial
    {
        $updatedTutorial = $this->updateAction->handle($tutorial, $data);
        $this->syncDamAssets($updatedTutorial, $data);
        $this->clearTutorialCache($tutorial);

        return $this->loadCurrentDamRelations($updatedTutorial);
    }

    /**
     * Delete a tutorial
     */
    public function deleteTutorial(Tutorial $tutorial): bool
    {
        $tutorial->dams()->delete();
        $result = $this->deleteAction->execute($tutorial);
        $this->clearTutorialCache($tutorial);
        return $result;
    }

    /**
     * Increment tutorial views
     */
    public function incrementViews(Tutorial $tutorial): void
    {
        $tutorial->incrementViews();
        $this->clearTutorialCache($tutorial);
    }

    /**
     * Get tutorial statistics
     */
    public function getTutorialStats(): array
    {
        $cacheKey = 'tutorial_stats';

        return CacheHelper::remember([], $cacheKey, 600, function () {
            return [
                'total' => Tutorial::count(),
                'published' => Tutorial::published()->count(),
                'draft' => Tutorial::draft()->count(),
                'archived' => Tutorial::archived()->count(),
                'total_views' => Tutorial::sum('views_count'),
                'total_likes' => Tutorial::sum('likes_count'),
                'by_type' => Tutorial::selectRaw('type, COUNT(*) as count')
                    ->groupBy('type')
                    ->pluck('count', 'type')
                    ->toArray(),
                'by_difficulty' => Tutorial::selectRaw('difficulty_level, COUNT(*) as count')
                    ->whereNotNull('difficulty_level')
                    ->groupBy('difficulty_level')
                    ->pluck('count', 'difficulty_level')
                    ->toArray(),
            ];
        });
    }

    /**
     * Get popular tutorials
     */
    public function getPopularTutorials(int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        $cacheKey = 'popular_tutorials_' . $limit;

        return CacheHelper::remember([], $cacheKey, 1800, function () use ($limit) {
            return Tutorial::published()
                ->with([
                    'dams' => fn ($query) => $query->where('is_current', true)->orderByDesc('id'),
                ])
                ->popular()
                ->limit($limit)
                ->get(['id', 'uuid', 'title', 'slug', 'thumbnail', 'views_count', 'likes_count']);
        });
    }

    /**
     * Clear tutorial cache
     */
    protected function clearTutorialCache(?Tutorial $tutorial = null): void
    {
        // Clear general caches
        CacheHelper::clearTags(['tutorials']);

        // Clear specific tutorial caches
        if ($tutorial) {
            CacheHelper::forget([], 'tutorial_uuid_' . $tutorial->uuid);
            CacheHelper::forget([], 'tutorial_slug_' . $tutorial->slug);
        }

        // Clear stats cache
        CacheHelper::forget([], 'tutorial_stats');

        // Clear popular tutorials cache
        CacheHelper::forget([], 'popular_tutorials_10');
    }

    protected function tutorialRelations(): array
    {
        return [
            'creator',
            'tags',
            'categories',
            'primaryCategory',
            'dams' => fn ($query) => $query->where('is_current', true)->orderByDesc('id'),
        ];
    }

    protected function loadCurrentDamRelations(Tutorial $tutorial): Tutorial
    {
        return $tutorial->fresh($this->tutorialRelations());
    }

    protected function syncDamAssets(Tutorial $tutorial, array $data): void
    {
        $assetMappings = [
            [
                'collection' => 'thumbnail',
                'key_field' => 'thumbnail',
            ],
            [
                'collection' => 'video_file',
                'key_field' => 'video_file',
            ],
        ];

        foreach ($assetMappings as $mapping) {
            $objectKey = data_get($data, $mapping['key_field']);

            if (! is_string($objectKey) || $objectKey === '') {
                continue;
            }

            $collection = $mapping['collection'];
            $fileName = basename($objectKey);

            $existingAsset = $tutorial->dams()
                ->where('collection_name', $collection)
                ->where('object_key', $objectKey)
                ->latest('id')
                ->first();

            $tutorial->dams()
                ->where('collection_name', $collection)
                ->where('is_current', true)
                ->when($existingAsset, fn ($query) => $query->where('id', '!=', $existingAsset->id))
                ->update(['is_current' => false]);

            if ($existingAsset) {
                $existingAsset->update([
                    'file_name' => $fileName,
                    'is_current' => true,
                ]);

                continue;
            }

            $this->damService->ingest([
                'damable_type' => Tutorial::class,
                'damable_id' => $tutorial->id,
                'collection_name' => $collection,
                'file_name' => $fileName,
                'object_key' => $objectKey,
                'is_current' => true,
            ]);
        }

        $tutorial->unsetRelation('dams');
    }
}
