<?php

declare(strict_types=1);

namespace App\Http\Controllers\V1\Post;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Post;
use App\Http\Requests\V1\Post\PostStoreRequest;
use App\Http\Requests\V1\Post\PostUpdateRequest;
use App\Http\Resources\V1\Post\PostResource;
use App\Services\Post\PostService;
use App\Services\Storage\S3UploadService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class PostApiController extends Controller
{
    protected S3UploadService $s3Service;
    protected PostService $postService;

    public function __construct(S3UploadService $s3Service, PostService $postService)
    {
        $this->s3Service = $s3Service;
        $this->postService = $postService;
    }

    ## Paginated (per_page + page, plus all filters)
// curl -G \
//   'https://license.real3dtech.com/api/v1/post' \
//   -H 'Accept: application/json' \
//   --data-urlencode 'per_page=20' \
//   --data-urlencode 'page=1' \
//   --data-urlencode 'latest=1' \
//   --data-urlencode 'start_date=2025-01-01' \
//   --data-urlencode 'end_date=2025-10-30' \
//   --data-urlencode 'tags=release,security' \
//   --data-urlencode 'categories=announcements,updates'


// ## Limit-based (returns a fixed number of items; pagination ignored)
// curl -G \
//   'https://license.real3dtech.com/api/v1/post' \
//   -H 'Accept: application/json' \
//   --data-urlencode 'limit=10' \
//   --data-urlencode 'latest=1' \
//   --data-urlencode 'start_date=2025-01-01' \
//   --data-urlencode 'end_date=2025-10-30' \
//   --data-urlencode 'tags=release,security' \
//   --data-urlencode 'categories=announcements,updates'

// # Without filters
//   curl -G \
//   'https://license.real3dtech.com/api/v1/post' \
//   -H 'Accept: application/json' \
//   --data-urlencode 'limit=10'


    /**
     * List published post with optional filters.
     *
     * Query params supported:
     * - per_page (int): pagination size (default 15)
     * - page (int)
     * - limit (int): return this many items (overrides pagination)
     * - latest (bool): if true order by created_at desc
     * - start_date, end_date (Y-m-d or ISO): filter created_at window
     * - tags (comma separated)
     * - categories (comma separated)
     */
    public function index(\App\Http\Requests\V1\Post\IndexRequest $request)
    {
        

        $filters = [
            'search' => $request->input('search'),
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
            'tags' => $request->input('tags'),
            'categories' => $request->input('categories'),
            'latest' => $request->boolean('latest'),
        ];

        $limit = $request->filled('limit') ? (int) $request->input('limit') : null;
        $perPage = (int) $request->input('per_page', 15);

        $result = $this->postService->getPublicPosts($filters, $perPage, $limit);

        if ($limit !== null) {
            $data = $result->map(fn ($item) => $this->transform($item));

            return response()->json(['data' => $data]);
        }

        return response()->json([
            'data' => $result->getCollection()->map(fn ($item) => $this->transform($item)),
            'meta' => [
                'current_page' => $result->currentPage(),
                'per_page' => $result->perPage(),
                'total' => $result->total(),
                'last_page' => $result->lastPage(),
            ],
        ]);
    }

    /**
     * Return single published post by id|uuid|slug.
     */
    public function show($id)
    {
        $post = $this->postService->getPublicPostByIdentifier((string) $id);

        if (! $post) {
            return response()->json(['message' => 'Not found'], 404);
        }

        return response()->json(['data' => $this->transformDetail($post)]);
    }

    /**
     * Get post by primary category slug
     */
    public function byCategory(\App\Http\Requests\V1\Post\ByCategoryRequest $request, string $categorySlug)
    {
        

        // Find category by slug
        $category = \App\Models\Category::where('slug', $categorySlug)->first();

        if (!$category) {
            return response()->json(['message' => 'Category not found'], 404);
        }

        $filters = [
            'category_slug' => $categorySlug,
            'latest' => $request->boolean('latest', true),
        ];

        $limit = $request->filled('limit') ? (int) $request->input('limit') : null;
        $perPage = (int) $request->input('per_page', 15);

        $result = $this->postService->getPublicPosts($filters, $perPage, $limit);

        if ($limit !== null) {
            $data = $result->map(fn ($item) => $this->transform($item));

            return response()->json(['data' => $data]);
        }

        return response()->json([
            'data' => $result->getCollection()->map(fn ($item) => $this->transform($item)),
            'meta' => [
                'current_page' => $result->currentPage(),
                'per_page' => $result->perPage(),
                'total' => $result->total(),
                'last_page' => $result->lastPage(),
            ],
        ]);
    }

    /**
     * Get published blogs
     */
    public function blogs(Request $request)
    {
        return $this->getByType($request, 'blog');
    }

    /**
     * Get published events
     */
    public function events(Request $request)
    {
        return $this->getByType($request, 'event');
    }

    /**
     * Get published news
     */
    public function news(Request $request)
    {
        return $this->getByType($request, 'news');
    }

    /**
     * Helper method to get content by type
     */
    protected function getByType(Request $request, string $type)
    {
        

        $filters = [
            'type' => $type,
            'search' => $request->input('search'),
            'latest' => $request->boolean('latest', true),
        ];

        $limit = $request->filled('limit') ? (int) $request->input('limit') : null;
        $perPage = (int) $request->input('per_page', 15);

        $result = $this->postService->getPublicPosts($filters, $perPage, $limit);

        if ($limit !== null) {
            $data = $result->map(fn ($item) => $this->transform($item));

            return response()->json(['data' => $data]);
        }

        return response()->json([
            'data' => $result->getCollection()->map(fn ($item) => $this->transform($item)),
            'meta' => [
                'current_page' => $result->currentPage(),
                'per_page' => $result->perPage(),
                'total' => $result->total(),
                'last_page' => $result->lastPage(),
            ],
        ]);
    }

    /**
     * Placeholder - not enabled via API for now.
     */
    public function store(Request $request)
    {
        return response()->json(['message' => 'Not implemented'], 405);
    }

    public function update(Request $request, $id)
    {
        return response()->json(['message' => 'Not implemented'], 405);
    }

    /**
     * Generate signed URL for post image (public endpoint)
     *
     * @deprecated Use /api/v1/assets/generate-url instead for new implementations.
     * This endpoint is kept for backward compatibility with existing post images.
     * Only allows post image paths for security.
     */
    public function generateImageUrl(Request $request): JsonResponse
    {
        

        $key = $request->input('key');
        $expiresIn = (int) $request->input('expires_in', 3600);

        // Security: Only allow post image paths
        // For other public images, use /api/v1/assets/generate-url
        $allowedPrefixes = [
            'posts/featured/',
            'posts/images/',
            // New path structure
            'images/posts/',
            'images/posts/featured/',
        ];

        $isAllowed = false;
        foreach ($allowedPrefixes as $prefix) {
            if (str_starts_with($key, $prefix)) {
                $isAllowed = true;
                break;
            }
        }

        if (!$isAllowed) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid image path. Use /api/v1/assets/generate-url for non-post images.'
            ], 403);
        }

        try {
            $url = $this->s3Service->generatePresignedDownloadUrl($key, $expiresIn);

            return response()->json([
                'success' => true,
                'data' => ['url' => $url]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate URL'
            ], 500);
        }
    }

    public function destroy($id)
    {
        return response()->json(['message' => 'Not implemented'], 405);
    }

    /**
     * Transform post model to API payload (summary)
     */
    protected function transform(Post $n): array
    {
        $base = config('app.url') ?: url('/');

        // Featured image: stored keys resolve through the public CDN; full URLs pass through.
        $featuredImageUrl = null;
        if ($n->featured_image && !str_starts_with($n->featured_image, 'http')) {
            $featuredImageUrl = config('app.cdn_url', 'https://cdn.htashop.com') . '/' . ltrim($n->featured_image, '/');
        } elseif ($n->featured_image && str_starts_with($n->featured_image, 'http')) {
            $featuredImageUrl = $n->featured_image;
        }

        // Generate frontend URL based on type
        $frontendBase = config('app.frontend_url', 'https://htashop.com');
        $slug = $n->slug ?? $n->uuid;
        $type = $n->type->value ?? (string)$n->type; // Get enum value or cast to string
        $categorySlug = $n->primaryCategory ? $n->primaryCategory->slug : 'general';

        if ($type === 'blog') {
            $frontendUrl = rtrim($frontendBase, '/') . '/blogs/' . $slug;
        } elseif ($type === 'news') {
            $frontendUrl = rtrim($frontendBase, '/') . '/news/' . $slug;
        } elseif ($type === 'event') {
            $frontendUrl = rtrim($frontendBase, '/') . '/events/' . $slug;
        } else {
            $frontendUrl = rtrim($frontendBase, '/') . '/content/' . $slug;
        }

        return [
            'id' => $n->id,
            'uuid' => $n->uuid,
            'slug' => $n->slug,
            'type' => $n->type,
            'title' => $n->title,
            'excerpt' => $n->excerpt,
            'featured_image' => $n->featured_image,
            'featured_image_url' => $featuredImageUrl,
            'published_at' => $n->sent_at ? $n->sent_at->toIso8601String() : ($n->created_at ? $n->created_at->toIso8601String() : null),
            'url' => $frontendUrl,
            'frontend_url' => $frontendUrl,
            'tags' => $n->tags ? $n->tags->pluck('name')->toArray() : [],
            'categories' => $n->categories ? $n->categories->pluck('name')->toArray() : [],
            'primary_category_id' => $n->primary_category_id,
            'primary_category' => $n->primaryCategory ? [
                'id' => $n->primaryCategory->id,
                'name' => $n->primaryCategory->name,
                'slug' => $n->primaryCategory->slug,
            ] : null,

            // For software compatibility
            'description' => $n->excerpt,
            'link' => $frontendUrl,
            'date' => $n->created_at ? $n->created_at->toIso8601String() : null,
            'category' => $n->categories?->first()?->name,
        ];
    }

    /**
     * Transform post model to API payload (full detail with content)
     */
    protected function transformDetail(Post $n): array
    {
        $base = config('app.url') ?: url('/');

        // Featured image: stored keys resolve through the public CDN; full URLs pass through.
        $featuredImageUrl = null;
        if ($n->featured_image && !str_starts_with($n->featured_image, 'http')) {
            $featuredImageUrl = config('app.cdn_url', 'https://cdn.htashop.com') . '/' . ltrim($n->featured_image, '/');
        } elseif ($n->featured_image && str_starts_with($n->featured_image, 'http')) {
            $featuredImageUrl = $n->featured_image;
        }

        // Generate frontend URL based on type
        $frontendBase = config('app.frontend_url', 'https://htashop.com');
        $slug = $n->slug ?? $n->uuid;
        $type = $n->type->value ?? (string)$n->type; // Get enum value or cast to string

        if ($type === 'blog') {
            $frontendUrl = rtrim($frontendBase, '/') . '/blogs/' . $slug;
        } elseif ($type === 'news') {
            $frontendUrl = rtrim($frontendBase, '/') . '/news/' . $slug;
        } elseif ($type === 'event') {
            $frontendUrl = rtrim($frontendBase, '/') . '/events/' . $slug;
        } else {
            $frontendUrl = rtrim($frontendBase, '/') . '/content/' . $slug;
        }

        return [
            'id' => $n->id,
            'uuid' => $n->uuid,
            'slug' => $n->slug,
            'type' => $n->type,
            'title' => $n->title,
            'excerpt' => $n->excerpt,
            'content' => $n->content,
            'featured_image' => $n->featured_image,
            'featured_image_url' => $featuredImageUrl,
            'published_at' => $n->sent_at ? $n->sent_at->toIso8601String() : ($n->created_at ? $n->created_at->toIso8601String() : null),
            'url' => $frontendUrl,
            'frontend_url' => $frontendUrl,
            'tags' => $n->tags ? $n->tags->pluck('name')->toArray() : [],
            'categories' => $n->categories ? $n->categories->pluck('name')->toArray() : [],
            'primary_category_id' => $n->primary_category_id,
            'primary_category' => $n->primaryCategory ? [
                'id' => $n->primaryCategory->id,
                'name' => $n->primaryCategory->name,
                'slug' => $n->primaryCategory->slug,
            ] : null,

            // For software compatibility
            'description' => $n->excerpt,
            'link' => $frontendUrl,
            'date' => $n->created_at ? $n->created_at->toIso8601String() : null,
            'category' => $n->categories?->first()?->name,
        ];
    }
}
