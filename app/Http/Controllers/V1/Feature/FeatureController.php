<?php

namespace App\Http\Controllers\V1\Feature;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Feature;
use App\Services\Feature\FeatureService;
use App\Http\Responses\V1\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FeatureController extends Controller
{
    public function __construct(
        protected FeatureService $featureService
    ) {}

    /**
     * List features, optionally filtered by category.
     * GET /api/v1/features?category_id=1
     */
    public function index(Request $request): JsonResponse
    {
        $categoryId = $request->integer('category_id');

        $features = Feature::query()
            ->when($categoryId, function ($query) use ($categoryId) {
                $topLevelId = $this->resolveTopLevelCategoryId($categoryId);
                $query->whereHas('categories', fn ($q) => $q->where('categories.id', $topLevelId));
            })
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        return ApiResponse::success($features, 'Features retrieved successfully');
    }

    /**
     * Walk up the category tree to the top-level parent, so features seeded on
     * top-level categories are returned for any of their sub-categories too.
     */
    private function resolveTopLevelCategoryId(int $categoryId): int
    {
        $category = Category::query()->find($categoryId);

        while ($category && $category->parent_id) {
            $category = $category->parent;
        }

        return $category?->id ?? $categoryId;
    }

    /**
     * Get popular features
     * GET /api/v1/features/popular
     */
    public function popular(\App\Http\Requests\V1\Feature\Popular\PopularRequest $request): JsonResponse
    {
        

        $type = $request->get('type', 'product');
        $limit = $request->get('limit', 20);

        $features = $this->featureService->getPopularFeatures($type, $limit);

        return response()->json([
            'success' => true,
            'data' => $features
        ]);
    }

    /**
     * Search features
     * GET /api/v1/features/search?q=keyword
     */
    public function search(\App\Http\Requests\V1\Feature\Search\SearchRequest $request): JsonResponse
    {
        

        $query = $request->get('q');
        $type = $request->get('type', 'product');
        $limit = $request->get('limit', 20);

        $features = $this->featureService->searchFeatures($query, $type, $limit);

        return response()->json([
            'success' => true,
            'data' => $features
        ]);
    }

    /**
     * Get a specific feature by type and slug
     * GET /api/v1/features/{type}/{slug}
     */
    public function show(string $type, string $slug): JsonResponse
    {
        $feature = $this->featureService->findBySlug($slug, $type);

        if (!$feature) {
            return response()->json([
                'success' => false,
                'message' => 'Feature not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $feature->load('products')
        ]);
    }

    /**
     * Get all items with a specific feature
     * GET /api/v1/features/{type}/{slug}/items/{itemType}
     */
    public function featuredItems(string $type, string $slug, string $itemType): JsonResponse
    {
        $feature = $this->featureService->findBySlug($slug, $type);

        if (!$feature) {
            return response()->json([
                'success' => false,
                'message' => 'Feature not found'
            ], 404);
        }

        // Get items based on type
        $items = match ($itemType) {
            'products' => $feature->products()->with(['brand', 'categories'])->paginate(20),
            default => []
        };

        return response()->json([
            'success' => true,
            'data' => [
                'feature' => $feature,
                'items' => $items
            ]
        ]);
    }

    /**
     * Create a new feature (admin only)
     * POST /api/v1/features
     */
    public function store(Request $request): JsonResponse
    {
        

        try {
            $feature = $this->featureService->createFeature($validator->validated());

            return response()->json([
                'success' => true,
                'message' => 'Feature created successfully',
                'data' => $feature
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create feature: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a feature (admin only)
     * DELETE /api/v1/features/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $feature = Feature::findOrFail($id);

            // Check if feature is in use
            if ($feature->usage_count > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete feature that is currently in use'
                ], 400);
            }

            $feature->delete();

            return response()->json([
                'success' => true,
                'message' => 'Feature deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete feature: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete all unused features (admin only)
     * DELETE /api/v1/features/cleanup
     */
    public function cleanup(Request $request): JsonResponse
    {
        

        try {
            $type = $request->get('type', 'product');
            $deleted = $this->featureService->deleteUnusedFeatures($type);

            return response()->json([
                'success' => true,
                'message' => "Deleted {$deleted} unused features"
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to cleanup features: ' . $e->getMessage()
            ], 500);
        }
    }
}
