<?php

namespace App\Http\Controllers\V1\Forum;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Forum\ForumCommentStoreRequest;
use App\Http\Requests\V1\Forum\ForumPostStoreRequest;
use App\Http\Requests\V1\Forum\ForumPostUpdateRequest;
use App\Http\Requests\V1\Forum\ForumReportStoreRequest;
use App\Http\Resources\V1\Forum\ForumCommentResource;
use App\Http\Resources\V1\Forum\ForumPostResource;
use App\Models\ForumComment;
use App\Models\ForumPost;
use App\Services\Forum\ForumCommentService;
use App\Services\Forum\ForumPostService;
use App\Services\Forum\ForumReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ForumPostController extends Controller
{
    public function __construct(
        protected ForumPostService $postService,
        protected ForumCommentService $commentService,
        protected ForumReportService $reportService,
    ) {}

    /**
     * List the authenticated user's posts.
     */
    public function myPosts(Request $request): JsonResponse
    {
        $filters = [
            'status' => $request->input('status'),
            'per_page' => $request->input('per_page', 15),
        ];

        $posts = $this->postService->getUserPosts($request->user()->id, $filters);

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
     * Create a new forum post.
     */
    public function store(ForumPostStoreRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $topic = \App\Models\ForumTopic::where('uuid', $validated['topic_uuid'])->first();

        if (!$topic || !$topic->is_active || $topic->is_locked) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot create posts in this topic.',
            ], 422);
        }

        // Replace uuid with resolved integer ID for the service layer
        $validated['topic_id'] = $topic->id;
        unset($validated['topic_uuid']);

        $post = $this->postService->create($validated, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Post created.',
            'data' => new ForumPostResource($post),
        ], 201);
    }

    /**
     * Get a single post by UUID (for editing by owner).
     */
    public function show(string $uuid): JsonResponse
    {
        $post = $this->postService->findByUuid($uuid);

        if (!$post) {
            return response()->json([
                'success' => false,
                'message' => 'Post not found.',
            ], 404);
        }

        $this->authorize('view', $post);

        return response()->json([
            'success' => true,
            'data' => new ForumPostResource($post),
        ]);
    }

    /**
     * Update a post.
     */
    public function update(ForumPostUpdateRequest $request, int $id): JsonResponse
    {
        $post = ForumPost::findOrFail($id);
        $this->authorize('update', $post);

        $data = $request->validated();

        // Regular users can only update title and body
        if (!$request->user()->hasAnyRole(['super-admin', 'admin'])) {
            $data = array_intersect_key($data, array_flip(['title', 'body', 'topic_id']));
        }

        $post = $this->postService->update($post, $data);

        return response()->json([
            'success' => true,
            'message' => 'Post updated.',
            'data' => new ForumPostResource($post),
        ]);
    }

    /**
     * Delete a post.
     */
    public function destroy(int $id): JsonResponse
    {
        $post = ForumPost::findOrFail($id);
        $this->authorize('delete', $post);

        $this->postService->delete($post);

        return response()->json([
            'success' => true,
            'message' => 'Post deleted.',
        ]);
    }

    /**
     * Toggle like on a post.
     */
    public function toggleLike(int $id, Request $request): JsonResponse
    {
        $post = ForumPost::where('id', $id)
            ->where('status', 'published')
            ->firstOrFail();

        $result = $this->postService->toggleLike($post, $request->user());

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * Add a comment to a post.
     */
    public function addComment(ForumCommentStoreRequest $request, int $postId): JsonResponse
    {
        $post = ForumPost::where('id', $postId)
            ->where('status', 'published')
            ->firstOrFail();

        if ($post->is_locked) {
            return response()->json([
                'success' => false,
                'message' => 'This post is locked and does not accept new comments.',
            ], 422);
        }

        // If parent_id is provided, verify it belongs to the same post
        if ($request->validated()['parent_id'] ?? null) {
            $parentComment = ForumComment::where('id', $request->validated()['parent_id'])
                ->where('post_id', $post->id)
                ->where('status', 'visible')
                ->first();

            if (!$parentComment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Parent comment not found.',
                ], 422);
            }
        }

        $comment = $this->commentService->create($post, $request->user(), $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Comment added.',
            'data' => new ForumCommentResource($comment),
        ], 201);
    }

    /**
     * Update a comment.
     */
    public function updateComment(Request $request, int $commentId): JsonResponse
    {
        $request->validate([
            'body' => ['required', 'string', 'min:1', 'max:5000'],
        ]);

        $comment = ForumComment::findOrFail($commentId);
        $this->authorize('update', $comment);

        $comment = $this->commentService->update($comment, $request->only('body'));

        return response()->json([
            'success' => true,
            'message' => 'Comment updated.',
            'data' => new ForumCommentResource($comment),
        ]);
    }

    /**
     * Delete a comment.
     */
    public function destroyComment(int $commentId): JsonResponse
    {
        $comment = ForumComment::findOrFail($commentId);
        $this->authorize('delete', $comment);

        $this->commentService->delete($comment);

        return response()->json([
            'success' => true,
            'message' => 'Comment deleted.',
        ]);
    }

    /**
     * Toggle like on a comment.
     */
    public function toggleCommentLike(int $commentId, Request $request): JsonResponse
    {
        $comment = ForumComment::where('id', $commentId)
            ->where('status', 'visible')
            ->firstOrFail();

        $result = $this->commentService->toggleLike($comment, $request->user());

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * Report content (post or comment).
     */
    public function report(ForumReportStoreRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $report = $this->reportService->createReport(
            $request->user(),
            $validated['reportable_type'],
            $validated['reportable_id'],
            $validated,
        );

        return response()->json([
            'success' => true,
            'message' => 'Report submitted. Our team will review it.',
        ], 201);
    }
}
