<?php

namespace App\Http\Controllers\V1\Category;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Category\StoreCategoryRequest;
use App\Http\Requests\V1\Category\UpdateCategoryRequest;
use App\Http\Resources\V1\Category\CategoryResource;
use App\Http\Responses\V1\ApiResponse;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    private const CACHE_TTL = 3600; // 1 hour

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

        if (!$category) {
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
                $data['slug'] = $originalSlug . '-' . $count;
                $count++;
            }
        }

        // Map 'order' to 'sort_order' if provided
        if (isset($data['order'])) {
            $data['sort_order'] = $data['order'];
            unset($data['order']);
        }

        // Set default sort_order if not provided
        if (!isset($data['sort_order'])) {
            $data['sort_order'] = 0;
        }

        // Calculate level and path
        if (!empty($data['parent_id'])) {
            $parent = Category::find($data['parent_id']);
            if ($parent) {
                $data['level'] = $parent->level + 1;
                $data['path'] = $parent->path . '/' . $parent->id;
            }
        } else {
            $data['level'] = 0;
            $data['path'] = '';
        }

        // Set default is_active if not provided
        if (!isset($data['is_active'])) {
            $data['is_active'] = true;
        }

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
        if (isset($data['name']) && !isset($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);

            // Ensure uniqueness
            $originalSlug = $data['slug'];
            $count = 1;
            while (Category::where('slug', $data['slug'])->where('id', '!=', $category->id)->exists()) {
                $data['slug'] = $originalSlug . '-' . $count;
                $count++;
            }
        }

        // Update level and path if parent changed
        if (isset($data['parent_id'])) {
            if ($data['parent_id']) {
                $parent = Category::find($data['parent_id']);
                if ($parent) {
                    $data['level'] = $parent->level + 1;
                    $data['path'] = $parent->path . '/' . $parent->id;
                }
            } else {
                $data['level'] = 0;
                $data['path'] = '';
            }

            // Update all descendants' paths
            $this->updateDescendantsPaths($category);
        }

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

        // Check if category is used by journals or manuscripts
        $journalsCount = $category->journals()->count();
        $manuscriptsCount = $category->manuscripts()->count();

        if ($journalsCount > 0 || $manuscriptsCount > 0) {
            return response()->json([
                'message' => 'Cannot delete category that is in use.',
                'details' => [
                    'journals_count' => $journalsCount,
                    'manuscripts_count' => $manuscriptsCount,
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
        Cache::forget('categories:all');
        Cache::forget('categories:tree');
    }

    /**
     * Update paths for all descendants when parent changes
     */
    private function updateDescendantsPaths(Category $category): void
    {
        $category->load('children');

        foreach ($category->children as $child) {
            $child->update([
                'level' => $category->level + 1,
                'path' => $category->path . '/' . $category->id,
            ]);

            // Recursively update grandchildren
            if ($child->children()->count() > 0) {
                $this->updateDescendantsPaths($child);
            }
        }
    }
}
