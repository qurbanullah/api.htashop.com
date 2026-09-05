<?php

declare(strict_types=1);

namespace App\Http\Controllers\V1\Post;

use App\Enums\PostTypeEnum;
use App\Enums\PostStatusEnum;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Post;
use App\Http\Requests\V1\Post\PostStoreRequest;
use App\Http\Requests\V1\Post\PostUpdateRequest;
use App\Http\Resources\V1\Post\PostResource;
use App\Services\Post\PostService;

class AdminPostController extends Controller
{
    public function __construct(
        protected PostService $postService
    ) {}

    /**
     * List all post (admin view with filters)
     * PROTECTED endpoint - requires authentication
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = [
                'search' => $request->input('search'),
                'status' => $request->input('status'),
                'type' => $request->input('type'),
                'date_from' => $request->input('date_from'),
                'date_to' => $request->input('date_to'),
            ];

            $filters = array_filter($filters);
            $perPage = (int) $request->input('per_page', 15);

            $post = $this->postService->getAllPost($filters, $perPage);

            return response()->json([
                'success' => true,
                'data' => PostResource::collection($post->items()),
                'meta' => [
                    'current_page' => $post->currentPage(),
                    'per_page' => $post->perPage(),
                    'total' => $post->total(),
                    'last_page' => $post->lastPage(),
                    'from' => $post->firstItem(),
                    'to' => $post->lastItem(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve post',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get post statistics
     * PROTECTED endpoint
     */
    public function stats(Request $request): JsonResponse
    {
        try {
            $stats = $this->postService->getPostStats();

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve statistics',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get post publishing trends
     * PROTECTED endpoint
     */
    public function publishingTrends(Request $request): JsonResponse
    {
        try {
            $days = (int) $request->input('days', 30);
            $trends = $this->postService->getPublishingTrends($days);

            return response()->json([
                'success' => true,
                'data' => $trends,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve publishing trends',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get available post types
     * PROTECTED endpoint
     */
    public function types(): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => PostTypeEnum::options(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve post types',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get available post statuses
     * PROTECTED endpoint
     */
    public function statuses(): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => PostStatusEnum::options(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve post statuses',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show a specific post (admin view)
     * PROTECTED endpoint
     */
    public function show(Request $request, string $uuid): JsonResponse
    {
        try {
            $post = $this->postService->getPostByUuid($uuid);

            if (!$post) {
                return response()->json([
                    'success' => false,
                    'message' => 'Post not found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => new PostResource($post),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve post',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created post
     * PROTECTED endpoint
     */
    public function store(PostStoreRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $validated['created_by'] = $request->user()->id;

            $post = $this->postService->createPost($validated);

            return response()->json([
                'success' => true,
                'message' => 'Post created successfully',
                'data' => new PostResource($post),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create post',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update an existing post
     * PROTECTED endpoint
     */
    public function update(PostUpdateRequest $request, string $uuid): JsonResponse
    {
        try {
            $post = $this->postService->getPostByUuid($uuid);

            if (!$post) {
                return response()->json([
                    'success' => false,
                    'message' => 'Post not found',
                ], 404);
            }

            $validated = $request->validated();
            $post = $this->postService->updatePost($post, $validated);

            return response()->json([
                'success' => true,
                'message' => 'Post updated successfully',
                'data' => new PostResource($post),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update post',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a post
     * PROTECTED endpoint
     */
    public function destroy(string $uuid): JsonResponse
    {
        try {
            $post = $this->postService->getPostByUuid($uuid);

            if (!$post) {
                return response()->json([
                    'success' => false,
                    'message' => 'Post not found',
                ], 404);
            }

            $this->postService->deletePost($post);

            return response()->json([
                'success' => true,
                'message' => 'Post deleted successfully',
            ]);
        } catch (\Exception $e) {
            // For business logic validation errors (like cannot delete sent post),
            // return 200 with success: false to avoid console errors
            if (str_contains($e->getMessage(), 'Cannot delete')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to delete post',
                    'error' => $e->getMessage(),
                ], 200);
            }

            // For actual server errors, return 500
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete post',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Send post (action endpoint)
     * PROTECTED endpoint
     */
    public function send(Request $request, string $uuid): JsonResponse
    {
        try {
            $post = $this->postService->getPostByUuid($uuid);

            if (!$post) {
                return response()->json([
                    'success' => false,
                    'message' => 'Post not found',
                ], 404);
            }

            $result = $this->postService->sendPost($post);

            return response()->json([
                'success' => true,
                'message' => 'Post sent successfully',
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send post',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Schedule post (action endpoint)
     * PROTECTED endpoint
     */
    public function schedule(Request $request, string $uuid): JsonResponse
    {
        try {
            $validated = $request->validate([
                'scheduled_at' => 'required|date|after:now',
            ]);

            $post = $this->postService->getPostByUuid($uuid);

            if (!$post) {
                return response()->json([
                    'success' => false,
                    'message' => 'Post not found',
                ], 404);
            }

            $scheduledAt = \Carbon\Carbon::parse($validated['scheduled_at']);
            $post = $this->postService->schedulePost($post, $scheduledAt);

            return response()->json([
                'success' => true,
                'message' => 'Post scheduled successfully',
                'data' => new PostResource($post),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to schedule post',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Toggle publish as blog (action endpoint)
     * PROTECTED endpoint
     */
    public function toggleBlogPublication(string $uuid): JsonResponse
    {
        try {
            $post = $this->postService->getPostByUuid($uuid);

            if (!$post) {
                return response()->json([
                    'success' => false,
                    'message' => 'Post not found',
                ], 404);
            }

            $post = $this->postService->publishAsBlog($post, !$post->is_published_as_blog);

            return response()->json([
                'success' => true,
                'message' => $post->is_published_as_blog ? 'Published as blog' : 'Unpublished from blog',
                'data' => new PostResource($post),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle blog publication',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
