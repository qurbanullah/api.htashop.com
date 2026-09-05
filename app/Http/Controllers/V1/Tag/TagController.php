<?php

namespace App\Http\Controllers\V1\Tag;

use App\Http\Controllers\Controller;
use App\Models\Tag;
use App\Services\Tag\TagService;
use App\Http\Resources\V1\Tags\TagResource;
use App\Http\Responses\V1\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;

class TagController extends Controller
{
    public function __construct(
        private TagService $tagService
    ) {}

    /**
     * Get all active tags (used by post/blog/news forms).
     */
    public function index(Request $request)
    {
        try {
            $tags = Tag::query()
                ->where('is_active', true)
                ->orderBy('sorting')
                ->get();

            return ApiResponse::success(
                TagResource::collection($tags),
                'Tags retrieved successfully'
            );
        } catch (\Exception $e) {
            Log::error('Failed to retrieve tags', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return ApiResponse::error('Failed to retrieve tags', null, 500);
        }
    }

    /**
     * Create a new tag
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:tags,name',
                'summary' => 'nullable|string|max:500',
                'description' => 'nullable|string',
            ]);

            $tag = Tag::create([
                'name' => $validated['name'],
                'slug' => Str::slug($validated['name']),
                'summary' => $validated['summary'] ?? null,
                'description' => $validated['description'] ?? null,
                'is_active' => true,
            ]);

            return ApiResponse::success(
                new TagResource($tag),
                'Tag created successfully',
                201
            );
        } catch (ValidationException $e) {
            return ApiResponse::error(
                'Validation failed',
                $e->errors(),
                422
            );
        } catch (\Exception $e) {
            Log::error('Failed to create tag', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return ApiResponse::error('Failed to create tag', null, 500);
        }
    }

    /**
     * Get popular tags
     *
     * GET /api/v1/tags/popular?type=product&limit=20
     */
    public function popular(Request $request): JsonResponse
    {
        $type = $request->get('type', 'product');
        $limit = min($request->get('limit', 20), 50);

        $tags = $this->tagService->getPopularTags($type, $limit);

        return response()->json([
            'data' => $tags,
            'meta' => [
                'type' => $type,
                'count' => $tags->count(),
            ]
        ]);
    }

    /**
     * Search tags
     *
     * GET /api/v1/tags/search?q=summer&type=product&limit=10
     */
    public function search(Request $request): JsonResponse
    {
        $query = $request->get('q', '');
        $type = $request->get('type', 'product');
        $limit = min($request->get('limit', 10), 30);

        if (empty($query)) {
            return response()->json([
                'data' => [],
                'meta' => ['message' => 'Search query required']
            ], 400);
        }

        $tags = $this->tagService->searchTags($query, $type, $limit);

        return response()->json([
            'data' => $tags,
            'meta' => [
                'query' => $query,
                'type' => $type,
                'count' => $tags->count(),
            ]
        ]);
    }

    /**
     * Get tag by slug
     *
     * GET /api/v1/tags/product/summer-collection
     */
    public function show(string $type, string $slug): JsonResponse
    {
        $tag = $this->tagService->findBySlug($slug, $type);

        if (!$tag) {
            return response()->json([
                'message' => 'Tag not found'
            ], 404);
        }

        return response()->json([
            'data' => $tag
        ]);
    }

    /**
     * Get items tagged with a specific tag
     *
     * GET /api/v1/tags/product/summer-collection/products
     */
    public function taggedItems(string $type, string $slug, string $itemType): JsonResponse
    {
        $tag = $this->tagService->findBySlug($slug, $type);

        if (!$tag) {
            return response()->json([
                'message' => 'Tag not found'
            ], 404);
        }

        // Map item type to model class
        $modelMap = [
            'products' => \App\Models\Product::class,
            // Add more as needed:
            // 'posts' => \App\Models\Post::class,
        ];

        $modelClass = $modelMap[$itemType] ?? null;

        if (!$modelClass) {
            return response()->json([
                'message' => 'Invalid item type'
            ], 400);
        }

        $items = $this->tagService->getTaggedItems($tag, $modelClass);

        return response()->json([
            'data' => $items,
            'meta' => [
                'tag' => $tag->name,
                'type' => $type,
                'item_type' => $itemType,
                'count' => $items->count(),
            ]
        ]);
    }

    /**
     * Create a new tag (admin only)
     *
     * POST /api/v1/tags
     */
    public function storeAdmin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:tags,slug',
            'type' => 'nullable|string|max:50',
        ]);

        $tag = $this->tagService->createTag($validated);

        return response()->json([
            'data' => $tag,
            'message' => 'Tag created successfully'
        ], 201);
    }

    /**
     * Delete a tag (admin only)
     *
     * DELETE /api/v1/tags/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $tag = Tag::findOrFail($id);

        // Detach from all taggables (this will decrement usage counts automatically)
        $tag->products()->detach();

        $tag->delete();

        return response()->json([
            'message' => 'Tag deleted successfully'
        ]);
    }

    /**
     * Cleanup unused tags (admin only)
     *
     * DELETE /api/v1/tags/cleanup?type=product
     */
    public function cleanup(Request $request): JsonResponse
    {
        $type = $request->get('type');

        $count = $this->tagService->cleanupUnusedTags($type);

        return response()->json([
            'message' => "Cleaned up {$count} unused tags",
            'count' => $count
        ]);
    }
}
