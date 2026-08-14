<?php

declare(strict_types=1);

namespace App\Http\Controllers\V1\Newsletter;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Newsletter;
use App\Http\Requests\Newsletters\NewsletterStoreRequest;
use App\Http\Requests\Newsletters\NewsletterUpdateRequest;
use App\Http\Resources\V1\Newsletters\NewsletterResource;
use App\Services\Newsletter\NewsletterService;
use App\Services\Storage\S3UploadService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class NewsletterApiController extends Controller
{
    protected S3UploadService $s3Service;

    public function __construct(S3UploadService $s3Service)
    {
        $this->s3Service = $s3Service;
    }

    ## Paginated (per_page + page, plus all filters)
// curl -G \
//   'https://license.real3dtech.com/api/v1/newsletters' \
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
//   'https://license.real3dtech.com/api/v1/newsletters' \
//   -H 'Accept: application/json' \
//   --data-urlencode 'limit=10' \
//   --data-urlencode 'latest=1' \
//   --data-urlencode 'start_date=2025-01-01' \
//   --data-urlencode 'end_date=2025-10-30' \
//   --data-urlencode 'tags=release,security' \
//   --data-urlencode 'categories=announcements,updates'

// # Without filters
//   curl -G \
//   'https://license.real3dtech.com/api/v1/newsletters' \
//   -H 'Accept: application/json' \
//   --data-urlencode 'limit=10'


    /**
     * List published newsletters with optional filters.
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
    public function index(\App\Http\Requests\V1\Newsletter\Index\IndexRequest $request)
    {
        

        $q = Newsletter::query()
            ->with(['categories', 'tags', 'primaryCategory'])
            ->whereIn('status', ['published', 'sent'])
            ->when($request->boolean('latest'), function ($q) {
                $q->orderBy('created_at', 'desc');
            });

        if ($request->filled('start_date')) {
            $q->where('created_at', '>=', $request->input('start_date'));
        }
        if ($request->filled('end_date')) {
            $q->where('created_at', '<=', $request->input('end_date'));
        }

        if ($request->filled('tags')) {
            $tags = array_filter(array_map('trim', explode(',', $request->input('tags'))));
            // Use relation lookup since tags are stored as morphToMany relation
            $q->whereHas('tags', function ($sub) use ($tags) {
                $sub->whereIn('name', $tags);
            });
        }

        if ($request->filled('categories')) {
            $cats = array_filter(array_map('trim', explode(',', $request->input('categories'))));
            // Use relation lookup since categories are stored as morphToMany relation
            $q->whereHas('categories', function ($sub) use ($cats) {
                $sub->whereIn('name', $cats);
            });
        }

        // If 'limit' is present, return that many items (no pagination)
        if ($request->filled('limit')) {
            $items = $q->limit((int) $request->input('limit'))->get();
            $data = $items->map(function ($item) {
                return $this->transform($item);
            });

            return response()->json([ 'data' => $data ]);
        }

        $perPage = (int) $request->input('per_page', 15);
        $paginator = $q->paginate($perPage)->appends($request->query());

        return response()->json([
            'data' => $paginator->getCollection()->map(function ($item) {
                return $this->transform($item);
            }),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * Return single published newsletter by id|uuid|slug.
     */
    public function show($id)
    {
        $newsletter = Newsletter::whereIn('status', ['published', 'sent'])
            ->with(['categories', 'tags', 'primaryCategory'])
            ->where(function ($q) use ($id) {
                $q->where('id', $id)
                  ->orWhere('uuid', $id)
                  ->orWhere('slug', $id);
            })->first();

        if (! $newsletter) {
            return response()->json(['message' => 'Not found'], 404);
        }

        return response()->json(['data' => $this->transformDetail($newsletter)]);
    }

    /**
     * Get newsletters by primary category slug
     */
    public function byCategory(\App\Http\Requests\V1\Newsletter\ByCategory\ByCategoryRequest $request, string $categorySlug)
    {
        

        // Find category by slug
        $category = \App\Models\Category::where('slug', $categorySlug)->first();

        if (!$category) {
            return response()->json(['message' => 'Category not found'], 404);
        }

        $q = Newsletter::query()
            ->with(['categories', 'tags', 'primaryCategory'])
            ->whereIn('status', ['published', 'sent'])
            ->where('primary_category_id', $category->id)
            ->when($request->boolean('latest', true), function ($q) {
                $q->orderBy('created_at', 'desc');
            });

        // If 'limit' is present, return that many items (no pagination)
        if ($request->filled('limit')) {
            $items = $q->limit((int) $request->input('limit'))->get();
            $data = $items->map(function ($item) {
                return $this->transform($item);
            });

            return response()->json(['data' => $data]);
        }

        $perPage = (int) $request->input('per_page', 15);
        $paginator = $q->paginate($perPage)->appends($request->query());

        return response()->json([
            'data' => $paginator->getCollection()->map(function ($item) {
                return $this->transform($item);
            }),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
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
        

        $q = Newsletter::query()
            ->with(['categories', 'tags', 'primaryCategory'])
            ->whereIn('status', ['published', 'sent'])
            ->where('type', $type)
            ->when($request->boolean('latest', true), function ($q) {
                $q->orderBy('created_at', 'desc');
            });

        // If 'limit' is present, return that many items (no pagination)
        if ($request->filled('limit')) {
            $items = $q->limit((int) $request->input('limit'))->get();
            $data = $items->map(function ($item) {
                return $this->transform($item);
            });

            return response()->json(['data' => $data]);
        }

        $perPage = (int) $request->input('per_page', 15);
        $paginator = $q->paginate($perPage)->appends($request->query());

        return response()->json([
            'data' => $paginator->getCollection()->map(function ($item) {
                return $this->transform($item);
            }),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
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
     * Generate signed URL for newsletter image (public endpoint)
     *
     * @deprecated Use /api/v1/assets/generate-url instead for new implementations.
     * This endpoint is kept for backward compatibility with existing newsletter images.
     * Only allows newsletter image paths for security.
     */
    public function generateImageUrl(Request $request): JsonResponse
    {
        

        $key = $request->input('key');
        $expiresIn = (int) $request->input('expires_in', 3600);

        // Security: Only allow newsletter image paths
        // For other public images, use /api/v1/assets/generate-url
        $allowedPrefixes = [
            'newsletters/featured/',
            'newsletters/images/',
            // New path structure
            'images/newsletters/',
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
                'message' => 'Invalid image path. Use /api/v1/assets/generate-url for non-newsletter images.'
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
     * Transform newsletter model to API payload (summary)
     */
    protected function transform(Newsletter $n): array
    {
        $base = config('app.url') ?: url('/');

        // Generate signed URL for featured image if exists
        $featuredImageUrl = null;
        if ($n->featured_image && !str_starts_with($n->featured_image, 'http')) {
            try {
                $featuredImageUrl = $this->s3Service->generatePresignedDownloadUrl($n->featured_image, 3600);
            } catch (\Exception $e) {
                Log::warning('Failed to generate signed URL for newsletter image', [
                    'newsletter_id' => $n->id,
                    'image' => $n->featured_image,
                    'error' => $e->getMessage()
                ]);
            }
        } elseif ($n->featured_image && str_starts_with($n->featured_image, 'http')) {
            $featuredImageUrl = $n->featured_image;
        }

        // Generate frontend URL based on type
        $frontendBase = config('app.frontend_url', 'https://volvicon.com');
        $slug = $n->slug ?? $n->uuid;
        $type = $n->type->value ?? (string)$n->type; // Get enum value or cast to string
        $categorySlug = $n->primaryCategory ? $n->primaryCategory->slug : 'general';

        if ($type === 'blog') {
            $frontendUrl = rtrim($frontendBase, '/') . '/blog/' . $slug;
        } elseif ($type === 'news') {
            $frontendUrl = rtrim($frontendBase, '/') . '/news/' . $categorySlug . '/' . $slug;
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
     * Transform newsletter model to API payload (full detail with content)
     */
    protected function transformDetail(Newsletter $n): array
    {
        $base = config('app.url') ?: url('/');

        // Generate signed URL for featured image if exists
        $featuredImageUrl = null;
        if ($n->featured_image && !str_starts_with($n->featured_image, 'http')) {
            try {
                $featuredImageUrl = $this->s3Service->generatePresignedDownloadUrl($n->featured_image, 3600);
            } catch (\Exception $e) {
                Log::warning('Failed to generate signed URL for newsletter image', [
                    'newsletter_id' => $n->id,
                    'image' => $n->featured_image,
                    'error' => $e->getMessage()
                ]);
            }
        } elseif ($n->featured_image && str_starts_with($n->featured_image, 'http')) {
            $featuredImageUrl = $n->featured_image;
        }

        // Generate frontend URL based on type
        $frontendBase = config('app.frontend_url', 'https://volvicon.com');
        $slug = $n->slug ?? $n->uuid;
        $type = $n->type->value ?? (string)$n->type; // Get enum value or cast to string

        $categorySlug = $n->primaryCategory ? $n->primaryCategory->slug : 'general';

        if ($type === 'blog') {
            $frontendUrl = rtrim($frontendBase, '/') . '/blog/' . $slug;
        } elseif ($type === 'news') {
            $frontendUrl = rtrim($frontendBase, '/') . '/news/' . $categorySlug . '/' . $slug;
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
