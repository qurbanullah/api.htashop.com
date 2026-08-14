<?php

namespace App\Http\Controllers\V1\Forum;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Forum\ForumCommentStoreRequest;
use App\Http\Resources\V1\Forum\ForumCommentResource;
use App\Models\ForumComment;
use App\Models\ForumPost;
use App\Services\Forum\ForumCommentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicForumCommentController extends Controller
{
    public function __construct(
        protected ForumCommentService $commentService,
    ) {}

    /**
     * Get paginated comments for a post (public).
     */
    public function index(string $slug, Request $request): JsonResponse
    {
        $post = ForumPost::where('slug', $slug)
            ->where('status', 'published')
            ->first();

        if (!$post) {
            return response()->json([
                'success' => false,
                'message' => 'Post not found.',
            ], 404);
        }

        $comments = $this->commentService->getPostComments($post, [
            'per_page' => $request->input('per_page', 20),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'data' => ForumCommentResource::collection($comments->items()),
                'current_page' => $comments->currentPage(),
                'last_page' => $comments->lastPage(),
                'per_page' => $comments->perPage(),
                'total' => $comments->total(),
            ],
        ]);
    }
}
