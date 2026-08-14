<?php

declare(strict_types=1);

namespace App\Http\Controllers\V1\Tutorial;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Resources\V1\Tutorial\TutorialResource;
use App\Services\Tutorial\TutorialService;
use App\Services\Storage\S3UploadService;
use App\Models\Tutorial;
use Illuminate\Support\Facades\Validator;

/**
 * PublicTutorialController - Handles public access to tutorials for Volvicon 3D Software
 *
 * This controller follows SOLID principles:
 * - Single Responsibility: Only handles HTTP layer for public tutorial access
 */
class PublicTutorialController extends Controller
{
    protected S3UploadService $s3Service;

    public function __construct(
        protected TutorialService $tutorialService,
        S3UploadService $s3Service
    ) {
        $this->s3Service = $s3Service;
    }

    /**
     * List published tutorials (public access)
     *
     * Query params supported:
     * - per_page (int): pagination size (default 15)
     * - page (int)
     * - limit (int): return this many items (overrides pagination)
     * - latest (bool): if true order by created_at desc
     *
     * PUBLIC endpoint - no authentication required
     */
    public function index(\App\Http\Requests\V1\Tutorial\Index\IndexRequest $request): JsonResponse
    {
        

        $q = Tutorial::query()
            ->with(['categories', 'tags', 'creator', 'primaryCategory'])
            ->where('status', 'published')
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
     * Display a specific published tutorial by slug or UUID
     * PUBLIC endpoint - no authentication required
     */
    public function show(string $identifier): JsonResponse
    {
        $tutorial = Tutorial::where('status', 'published')
            ->with(['categories', 'tags', 'creator', 'primaryCategory'])
            ->where(function ($q) use ($identifier) {
                $q->where('slug', $identifier)
                  ->orWhere('uuid', $identifier)
                  ->orWhere('id', $identifier);
            })->first();

        if (!$tutorial) {
            return response()->json(['message' => 'Tutorial not found'], 404);
        }

        // Increment views
        $this->tutorialService->incrementViews($tutorial);

        return response()->json(['data' => $this->transformDetail($tutorial)]);
    }

    /**
     * Get popular tutorials
     * PUBLIC endpoint
     */
    public function popular(\App\Http\Requests\V1\Tutorial\PopularRequest $request): JsonResponse
    {
        

        $limit = (int) $request->input('limit', 10);
        $tutorials = Tutorial::where('status', 'published')
            ->with(['categories', 'tags', 'creator', 'primaryCategory'])
            ->orderBy('views_count', 'desc')
            ->limit($limit)
            ->get();

        $data = $tutorials->map(function ($item) {
            return $this->transform($item);
        });

        return response()->json(['data' => $data]);
    }

    /**
     * Get tutorials by category (slug or ID)
     * PUBLIC endpoint
     */
    public function byCategory(Request $request, string $categoryIdentifier): JsonResponse
    {
        

        // Find category by slug or ID
        $category = \App\Models\Category::where(function($q) use ($categoryIdentifier) {
            $q->where('slug', $categoryIdentifier)
              ->orWhere('id', $categoryIdentifier);
        })->first();

        if (!$category) {
            return response()->json(['message' => 'Category not found'], 404);
        }

        $q = Tutorial::query()
            ->with(['categories', 'tags', 'creator', 'primaryCategory'])
            ->where('status', 'published')
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

            return response()->json([
                'data' => $data,
                'category' => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                ]
            ]);
        }

        $perPage = (int) $request->input('per_page', 15);
        $paginator = $q->paginate($perPage)->appends($request->query());

        return response()->json([
            'data' => $paginator->getCollection()->map(function ($item) {
                return $this->transform($item);
            }),
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
            ],
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * Transform tutorial model to API payload (summary)
     */
    protected function transform(Tutorial $t): array
    {
        // Generate signed URL for thumbnail if exists
        $thumbnailUrl = null;
        if ($t->thumbnail && !str_starts_with($t->thumbnail, 'http')) {
            try {
                $thumbnailUrl = $this->s3Service->generatePresignedDownloadUrl($t->thumbnail, 3600);
            } catch (\Exception $e) {
                \Log::error('Failed to generate thumbnail URL', [
                    'tutorial_id' => $t->id,
                    'thumbnail' => $t->thumbnail,
                    'error' => $e->getMessage()
                ]);
            }
        } elseif ($t->thumbnail && str_starts_with($t->thumbnail, 'http')) {
            $thumbnailUrl = $t->thumbnail;
        }

        // Format duration
        $durationFormatted = null;
        if ($t->duration) {
            $hours = floor($t->duration / 3600);
            $minutes = floor(($t->duration % 3600) / 60);
            $seconds = $t->duration % 60;

            if ($hours > 0) {
                $durationFormatted = sprintf('%d:%02d:%02d', $hours, $minutes, $seconds);
            } else {
                $durationFormatted = sprintf('%d:%02d', $minutes, $seconds);
            }
        }

        // Generate frontend URL with category
        $frontendBase = config('app.frontend_url', 'https://volvicon.com');
        $slug = $t->slug ?? $t->uuid;
        $categorySlug = $t->primaryCategory ? $t->primaryCategory->slug : 'general';
        $frontendUrl = rtrim($frontendBase, '/') . '/learning-center/' . $categorySlug . '/' . $slug;

        return [
            'id' => $t->id,
            'uuid' => $t->uuid,
            'slug' => $t->slug,
            'type' => $t->type,
            'type_label' => $t->type->label() ?? (string)$t->type,
            'title' => $t->title,
            'excerpt' => $t->excerpt,
            'thumbnail' => $t->thumbnail,
            'thumbnail_url' => $thumbnailUrl,
            'has_video' => (bool)($t->video_file || $t->youtube_url),
            'difficulty_level' => $t->difficulty_level,
            'duration_formatted' => $durationFormatted,
            'views_count' => $t->views_count,
            'published_at' => $t->published_at ? $t->published_at->toIso8601String() : ($t->created_at ? $t->created_at->toIso8601String() : null),
            'frontend_url' => $frontendUrl,
            'primary_category' => $t->primaryCategory ? [
                'id' => $t->primaryCategory->id,
                'name' => $t->primaryCategory->name,
                'slug' => $t->primaryCategory->slug,
            ] : null,
            'tags' => $t->tags ? $t->tags->map(fn($tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
                'slug' => $tag->slug,
            ])->toArray() : [],
            'categories' => $t->categories ? $t->categories->map(fn($cat) => [
                'id' => $cat->id,
                'name' => $cat->name,
                'slug' => $cat->slug,
            ])->toArray() : [],
        ];
    }

    /**
     * Transform tutorial model to API payload (full detail with content and video)
     */
    protected function transformDetail(Tutorial $t): array
    {
        // Generate signed URL for thumbnail if exists
        $thumbnailUrl = null;
        if ($t->thumbnail && !str_starts_with($t->thumbnail, 'http')) {
            try {
                $thumbnailUrl = $this->s3Service->generatePresignedDownloadUrl($t->thumbnail, 3600);
            } catch (\Exception $e) {
                \Log::error('Failed to generate thumbnail URL', [
                    'tutorial_id' => $t->id,
                    'thumbnail' => $t->thumbnail,
                    'error' => $e->getMessage()
                ]);
            }
        } elseif ($t->thumbnail && str_starts_with($t->thumbnail, 'http')) {
            $thumbnailUrl = $t->thumbnail;
        }

        // Generate signed URL for video file if exists (not YouTube)
        $videoUrl = null;
        if ($t->video_file && !str_starts_with($t->video_file, 'http')) {
            try {
                // Videos require authentication, so this might fail for public access
                // Frontend will handle authentication if needed
                $videoUrl = $this->s3Service->generatePresignedDownloadUrl($t->video_file, 7200); // 2 hours for video
            } catch (\Exception $e) {
                \Log::warning('Failed to generate video URL (may require auth)', [
                    'tutorial_id' => $t->id,
                    'video_file' => $t->video_file,
                    'error' => $e->getMessage()
                ]);
            }
        } elseif ($t->video_file && str_starts_with($t->video_file, 'http')) {
            $videoUrl = $t->video_file;
        }

        // Format duration
        $durationFormatted = null;
        if ($t->duration) {
            $hours = floor($t->duration / 3600);
            $minutes = floor(($t->duration % 3600) / 60);
            $seconds = $t->duration % 60;

            if ($hours > 0) {
                $durationFormatted = sprintf('%d:%02d:%02d', $hours, $minutes, $seconds);
            } else {
                $durationFormatted = sprintf('%d:%02d', $minutes, $seconds);
            }
        }

        // Generate frontend URL with category
        $frontendBase = config('app.frontend_url', 'https://volvicon.com');
        $slug = $t->slug ?? $t->uuid;
        $categorySlug = $t->primaryCategory ? $t->primaryCategory->slug : 'general';
        $frontendUrl = rtrim($frontendBase, '/') . '/learning-center/' . $categorySlug . '/' . $slug;

        return [
            'id' => $t->id,
            'uuid' => $t->uuid,
            'type' => $t->type,
            'type_label' => $t->type->label() ?? (string)$t->type,
            'title' => $t->title,
            'slug' => $t->slug,
            'excerpt' => $t->excerpt,
            'content' => $t->content,
            'thumbnail' => $t->thumbnail,
            'thumbnail_url' => $thumbnailUrl,
            'video_file' => $t->video_file,
            'video_url' => $videoUrl,
            'youtube_url' => $t->youtube_url,
            'video_source' => $t->youtube_url ?: $t->video_file,
            'has_video' => (bool)($t->video_file || $t->youtube_url),
            'duration' => $t->duration,
            'duration_formatted' => $durationFormatted,
            'difficulty_level' => $t->difficulty_level,
            'status' => $t->status,
            'status_label' => $t->status->label() ?? (string)$t->status,
            'views_count' => $t->views_count,
            'likes_count' => $t->likes_count,
            'published_at' => $t->published_at ? $t->published_at->toIso8601String() : null,
            'created_at' => $t->created_at->toIso8601String(),
            'updated_at' => $t->updated_at->toIso8601String(),
            'metadata' => $t->metadata,
            'frontend_url' => $frontendUrl,
            'primary_category' => $t->primaryCategory ? [
                'id' => $t->primaryCategory->id,
                'name' => $t->primaryCategory->name,
                'slug' => $t->primaryCategory->slug,
            ] : null,
            'creator' => $t->creator ? [
                'id' => $t->creator->id,
                'uuid' => $t->creator->uuid,
                'name' => $t->creator->name,
                'email' => $t->creator->email,
                'avatar' => $t->creator->avatar,
            ] : null,
            'tags' => $t->tags ? $t->tags->map(fn($tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
                'slug' => $tag->slug,
            ])->toArray() : [],
            'categories' => $t->categories ? $t->categories->map(fn($cat) => [
                'id' => $cat->id,
                'name' => $cat->name,
                'slug' => $cat->slug,
            ])->toArray() : [],
        ];
    }
}
