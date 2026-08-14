<?php

namespace App\Http\Controllers\V1\Comment;

use App\Http\Controllers\Controller;
use App\Http\Resources\CommentResource;
use App\Services\Comment\CommentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Validator;

class CommentController extends Controller
{
    public function __construct(
        protected CommentService $commentService
    ) {}

    /**
     * Get all comments for a specific entity
     */
    public function index(\App\Http\Requests\V1\Comment\Index\IndexRequest $request): AnonymousResourceCollection|JsonResponse
    {
        

        // Get commentable entity
        $commentableType = $request->commentable_type;
        $commentableClass = match($commentableType) {
            'manuscript' => \App\Models\Manuscript::class,
            'reviewer' => \App\Models\Reviewer::class,
            default => null,
        };

        if (!$commentableClass) {
            return response()->json([
                'message' => 'Invalid commentable type',
            ], 400);
        }

        $commentable = $commentableClass::find($request->commentable_id);

        if (!$commentable) {
            return response()->json([
                'message' => 'Commentable entity not found',
            ], 404);
        }

        $filters = [];
        if ($request->has('include_internal')) {
            $filters['is_internal'] = $request->boolean('include_internal');
        }

        $comments = $this->commentService->getComments(
            $commentable,
            $filters
        );

        return CommentResource::collection($comments);
    }

    /**
     * Get a specific comment
     */
    public function show(int $id): CommentResource|JsonResponse
    {
        try {
            $comment = $this->commentService->getComment($id, request()->user());
            return new CommentResource($comment);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Comment not found or access denied',
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Create a new comment
     */
    public function store(\App\Http\Requests\V1\Comment\Store\StoreRequest $request): JsonResponse
    {
        

        try {
            // Get commentable entity
            $commentableType = $request->commentable_type;
            $commentableClass = match($commentableType) {
                'manuscript' => \App\Models\Manuscript::class,
                'reviewer' => \App\Models\Reviewer::class,
                default => null,
            };

            if (!$commentableClass) {
                return response()->json([
                    'message' => 'Invalid commentable type',
                ], 400);
            }

            $commentable = $commentableClass::findOrFail($request->commentable_id);

            $comment = $this->commentService->createComment(
                $commentable,
                $request->user(),
                $request->content,
                array_filter([
                    'is_internal' => $request->boolean('is_internal', false),
                    'attachments' => $request->attachments,
                    'revision_ids' => $request->revision_ids,
                ])
            );

            return response()->json([
                'message' => 'Comment created successfully',
                'data' => new CommentResource($comment),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create comment',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reply to a comment
     */
    public function reply(\App\Http\Requests\V1\Comment\Reply\ReplyRequest $request, int $parentId): JsonResponse
    {
        

        try {
            $parentComment = \App\Models\Comment::findOrFail($parentId);

            $comment = $this->commentService->replyToComment(
                $parentComment,
                $request->user(),
                $request->content,
                array_filter([
                    'is_internal' => $request->boolean('is_internal', false),
                    'attachments' => $request->attachments,
                ])
            );

            return response()->json([
                'message' => 'Reply added successfully',
                'data' => new CommentResource($comment),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to add reply',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update a comment
     */
    public function update(Request $request, int $id): JsonResponse
    {
        

        try {
            $commentModel = \App\Models\Comment::findOrFail($id);

            $comment = $this->commentService->updateComment(
                $commentModel,
                $request->user(),
                $request->only(['content', 'attachments'])
            );

            return response()->json([
                'message' => 'Comment updated successfully',
                'data' => new CommentResource($comment),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update comment',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Delete a comment
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $comment = \App\Models\Comment::findOrFail($id);
            $this->commentService->deleteComment($comment, $request->user());

            return response()->json([
                'message' => 'Comment deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete comment',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Mark a comment as read
     */
    public function markAsRead(Request $request, int $id): JsonResponse
    {
        try {
            $comment = \App\Models\Comment::findOrFail($id);
            $this->commentService->markAsRead($comment, $request->user());

            return response()->json([
                'message' => 'Comment marked as read',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to mark comment as read',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Mark all comments as read for an entity
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        

        try {
            // Get commentable entity
            $commentableType = $request->commentable_type;
            $commentableClass = match($commentableType) {
                'manuscript' => \App\Models\Manuscript::class,
                'reviewer' => \App\Models\Reviewer::class,
                default => null,
            };

            if (!$commentableClass) {
                return response()->json([
                    'message' => 'Invalid commentable type',
                ], 400);
            }

            $commentable = $commentableClass::findOrFail($request->commentable_id);

            $count = $this->commentService->markAllAsRead($commentable, $request->user());

            return response()->json([
                'message' => 'All comments marked as read',
                'count' => $count,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to mark comments as read',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get unread comments count
     */
    public function unreadCount(Request $request): JsonResponse
    {
        

        try {
            // Get commentable entity
            $commentableType = $request->commentable_type;
            $commentableClass = match($commentableType) {
                'manuscript' => \App\Models\Manuscript::class,
                'reviewer' => \App\Models\Reviewer::class,
                default => null,
            };

            if (!$commentableClass) {
                return response()->json([
                    'message' => 'Invalid commentable type',
                ], 400);
            }

            $commentable = $commentableClass::findOrFail($request->commentable_id);

            $count = $this->commentService->getUnreadCount($commentable, $request->user());

            return response()->json([
                'count' => $count,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to get unread count',
                'error' => $e->getMessage(),
            ], 400);
        }
    }
}
