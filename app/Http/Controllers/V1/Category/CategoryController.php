<?php

namespace App\Http\Controllers\V1\Category;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Category\StoreCategoryRequest;
use App\Http\Requests\V1\Category\UpdateCategoryRequest;
use App\Http\Requests\V1\Category\UpdateFeaturedCategoriesRequest;
use App\Http\Resources\V1\Category\CategoryResource;
use App\Http\Responses\V1\ApiResponse;
use App\Models\Category;
use App\Support\Catalog\CategoryCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    private const CACHE_TTL = 3600; // 1 hour

    /**
     * Admin listing: every category (including inactive) in one flat payload.
     *
     * The admin UI builds the tree client-side so it can render the whole
     * hierarchy without the public tree endpoint's depth limit and active-only
     * filter. Counts come free with the query and drive the row actions.
     */
    public function adminIndex(): JsonResponse
    {
        $categories = Category::query()
            ->withCount(['children', 'products'])
            ->orderBy('level')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return ApiResponse::success([
            'categories' => $categories,
            'total' => $categories->count(),
            'root_count' => $categories->whereNull('parent_id')->count(),
            'active_count' => $categories->where('is_active', true)->count(),
            'featured_count' => $categories->where('is_featured', true)->count(),
        ], 'Categories retrieved successfully');
    }

    /**
     * Display a listing of categories
     */
    public function index(): JsonResponse
    {
        // Check if we want all categories (flat list) or just root categories
        $flat = request()->get('flat', false);

        if ($flat) {
            // Return all categories in a flat list for multi-select dropdowns
            $categories = Cache::remember('categories:flat', self::CACHE_TTL, function () {
                return Category::where('is_active', true)
                    ->orderBy('level')
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->get();
            });
        } else {
            // Return only root categories with children (hierarchical)
            $categories = Cache::remember('categories:all', self::CACHE_TTL, function () {
                return Category::with(['children'])
                    ->whereNull('parent_id')
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->get();
            });
        }

        return ApiResponse::success(
            CategoryResource::collection($categories),
            'Categories retrieved successfully'
        );
    }

    /**
     * Display the specified category with products
     */
    public function show(string $slugOrId): JsonResponse
    {
        $category = is_numeric($slugOrId)
            ? Category::with(['children', 'parent'])->find($slugOrId)
            : Category::with(['children', 'parent'])->where('slug', $slugOrId)->first();

        if (! $category) {
            return response()->json([
                'message' => 'Category not found',
            ], 404);
        }

        return response()->json([
            'data' => new CategoryResource($category),
        ]);
    }

    /**
     * Get category tree (hierarchical structure)
     */
    public function tree(): JsonResponse
    {
        $tree = Cache::remember('categories:tree', self::CACHE_TTL, function () {
            // Load all active categories recursively
            return Category::with(['children' => function ($query) {
                $query->where('is_active', true)
                    ->with(['children' => function ($q) {
                        $q->where('is_active', true)
                            ->with(['children' => function ($qu) {
                                $qu->where('is_active', true)
                                    ->orderBy('sort_order')
                                    ->orderBy('name');
                            }])
                            ->orderBy('sort_order')
                            ->orderBy('name');
                    }])
                    ->orderBy('sort_order')
                    ->orderBy('name');
            }])
                ->whereNull('parent_id')
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();
        });

        return ApiResponse::success(
            CategoryResource::collection($tree),
            'Category tree retrieved successfully'
        );
    }

    /**
     * Store a newly created category
     */
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Auto-generate slug if not provided
        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);

            // Ensure uniqueness
            $originalSlug = $data['slug'];
            $count = 1;
            while (Category::where('slug', $data['slug'])->exists()) {
                $data['slug'] = $originalSlug.'-'.$count;
                $count++;
            }
        }

        // Map 'order' to 'sort_order' if provided
        if (isset($data['order'])) {
            $data['sort_order'] = $data['order'];
            unset($data['order']);
        }

        // Set default sort_order if not provided
        if (! isset($data['sort_order'])) {
            $data['sort_order'] = 0;
        }

        // Set default is_active if not provided
        if (! isset($data['is_active'])) {
            $data['is_active'] = true;
        }

        if (array_key_exists('code', $data)) {
            $data['code'] = $data['code'] ? Str::upper($data['code']) : null;
        }

        // Level and path are derived and persisted by the model's saved hook.
        $category = Category::create($data);

        // Clear cache
        $this->clearCache();

        // Load relationships for response
        $category->load(['parent', 'children']);

        return response()->json([
            'message' => 'Category created successfully',
            'data' => new CategoryResource($category),
        ], 201);
    }

    /**
     * Update the specified category
     */
    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        $data = $request->validated();

        // Map 'order' to 'sort_order' if provided
        if (isset($data['order'])) {
            $data['sort_order'] = $data['order'];
            unset($data['order']);
        }

        // Auto-generate slug if name changed and slug not provided
        if (array_key_exists('slug', $data) && blank($data['slug'])) {
            unset($data['slug']);
        }

        if (isset($data['name']) && ! isset($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);

            // Ensure uniqueness
            $originalSlug = $data['slug'];
            $count = 1;
            while (Category::where('slug', $data['slug'])->where('id', '!=', $category->id)->exists()) {
                $data['slug'] = $originalSlug.'-'.$count;
                $count++;
            }
        }

        if (array_key_exists('code', $data)) {
            $data['code'] = $data['code'] ? Str::upper($data['code']) : null;
        }

        // Level, path and the descendants' paths are handled by the model's
        // saved hook so a moved subtree never keeps stale paths.
        $category->update($data);

        // Clear cache
        $this->clearCache();

        // Reload relationships
        $category->load(['parent', 'children']);

        return response()->json([
            'message' => 'Category updated successfully',
            'data' => new CategoryResource($category),
        ]);
    }

    /**
     * List root categories together with their top-navigation curation state.
     *
     * The storefront nav is curated manually (seasonally, around launches,
     * etc.), so editors need the whole root list — not just the current
     * selection — to swap categories in and out.
     */
    public function featured(): JsonResponse
    {
        return ApiResponse::success(
            $this->featuredPayload(),
            'Featured categories retrieved successfully'
        );
    }

    /**
     * Replace the featured top-navigation selection and its order.
     */
    public function updateFeatured(UpdateFeaturedCategoriesRequest $request): JsonResponse
    {
        /** @var array<int, int> $ids */
        $ids = array_values(array_map('intval', (array) $request->validated('category_ids')));

        DB::transaction(function () use ($ids): void {
            // Everything outside the submitted list drops out of the pool.
            Category::query()
                ->whereNull('parent_id')
                ->whereNotIn('id', $ids)
                ->where(fn ($query) => $query->where('is_featured', true)->orWhere('featured_rank', '>', 0))
                ->update([
                    'is_featured' => false,
                    'featured_rank' => 0,
                ]);

            foreach ($ids as $index => $id) {
                Category::query()->whereKey($id)->update([
                    'is_featured' => true,
                    'featured_rank' => $index + 1,
                ]);
            }
        });

        $this->clearCache();

        return ApiResponse::success(
            $this->featuredPayload(),
            'Top navigation updated successfully'
        );
    }

    /**
     * Shape shared by the featured endpoints so the admin client can hydrate
     * its editor from a single response.
     */
    private function featuredPayload(): array
    {
        $categories = Category::query()
            ->whereNull('parent_id')
            ->orderByDesc('is_featured')
            ->orderBy('featured_rank')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'is_active', 'is_featured', 'featured_rank']);

        return [
            'categories' => $categories,
            'limit' => (int) config('catalog.top_nav.limit', 6),
            'featured_count' => $categories->where('is_featured', true)->count(),
        ];
    }

    /**
     * Remove the specified category
     */
    public function destroy(Category $category): JsonResponse
    {
        // Check if category has children
        if ($category->children()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete category with subcategories. Please delete or move subcategories first.',
            ], 422);
        }

        // Check if category is used by products. The polymorphic pivot has no
        // FK, so deleting would silently orphan those links.
        $productsCount = $category->products()->count();

        if ($productsCount > 0) {
            return response()->json([
                'message' => 'Cannot delete a category that is still assigned to products. Reassign those products first.',
                'details' => [
                    'products_count' => $productsCount,
                ],
            ], 422);
        }

        $category->delete();

        // Clear cache
        $this->clearCache();

        return response()->json([
            'message' => 'Category deleted successfully',
        ]);
    }

    /**
     * Clear all category-related caches
     */
    private function clearCache(): void
    {
        CategoryCache::flush();
    }
}
