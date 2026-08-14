<?php

namespace App\Http\Controllers\V1\Forum;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Forum\ForumTopicResource;
use App\Http\Resources\V1\Forum\ForumPostResource;
use App\Models\ForumPost;
use App\Services\Forum\ForumPostService;
use App\Services\Forum\ForumTopicService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicForumController extends Controller
{
    public function __construct(
        protected ForumTopicService $topicService,
        protected ForumPostService $postService,
    ) {}

    /**
     * List all active topics with post counts.
     */
    public function topics(): JsonResponse
    {
        $topics = $this->topicService->getActiveTopics();

        return response()->json([
            'success' => true,
            'data' => ForumTopicResource::collection($topics),
        ]);
    }

    /**
     * Get a single topic by slug.
     */
    public function topicBySlug(string $slug): JsonResponse
    {
        $topic = $this->topicService->findBySlug($slug);

        if (!$topic) {
            return response()->json([
                'success' => false,
                'message' => 'Topic not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new ForumTopicResource($topic),
        ]);
    }

    /**
     * List published posts with optional filters.
     */
    public function posts(Request $request): JsonResponse
    {
        $filters = [
            'topic_slug' => $request->input('topic'),
            'topic_id' => $request->input('topic_id'),
            'search' => $request->input('search'),
            'sort' => $request->input('sort', 'latest'),
            'featured' => $request->boolean('featured'),
            'per_page' => $request->input('per_page', 15),
        ];

        $posts = $this->postService->getPublicPosts($filters);

        return response()->json([
            'success' => true,
            'data' => [
                'data' => ForumPostResource::collection($posts->items()),
                'current_page' => $posts->currentPage(),
                'last_page' => $posts->lastPage(),
                'per_page' => $posts->perPage(),
                'total' => $posts->total(),
            ],
        ]);
    }

    /**
     * Get a single post by slug (public view).
     */
    public function postBySlug(string $slug): JsonResponse
    {
        $post = $this->postService->findBySlug($slug);

        if (!$post || $post->status->value !== 'published') {
            return response()->json([
                'success' => false,
                'message' => 'Post not found.',
            ], 404);
        }

        // Increment view count
        $this->postService->incrementViews($post);

        return response()->json([
            'success' => true,
            'data' => new ForumPostResource($post),
        ]);
    }
}
