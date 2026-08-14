<?php

namespace App\Http\Controllers\V1\Feedback;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Feedback\ReplyFeedbackRequest;
use App\Http\Requests\V1\Feedback\StoreFeedbackCommentRequest;
use App\Http\Resources\V1\Feedback\CommentResource;
use App\Http\Resources\V1\Feedback\FeedbackResource;
use App\Http\Resources\V1\Feedback\FeedbackCollection;
use App\Services\Feedbacks\FeedbackService;
use App\Jobs\Feedback\SendFeedbackReplyEmailJob;
use App\Http\Responses\V1\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class FeedbackAdminController extends Controller
{
    protected FeedbackService $feedbackService;

    public function __construct(FeedbackService $feedbackService)
    {
        $this->feedbackService = $feedbackService;
    }

    /**
     * Get all feedbacks with filters (Admin only)
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = [
                'search' => $request->input('search'),
                'status' => $request->input('status'),
                'type' => $request->input('type'),
                'priority' => $request->input('priority'),
            ];

            $feedbacks = $this->feedbackService->show([
                'filters' => $filters,
                'rows' => $request->input('per_page', 15),
            ]);

            return ApiResponse::success(
                new FeedbackCollection($feedbacks),
                'Feedbacks retrieved successfully'
            );
        } catch (\Exception $e) {
            Log::error('Failed to retrieve feedbacks', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error('Failed to retrieve feedbacks', null, 500);
        }
    }

    /**
     * Get single feedback by UUID
     */
    public function show(string $uuid): JsonResponse
    {
        try {
            $feedback = $this->feedbackService->findByUuid($uuid);

            if (!$feedback) {
                return ApiResponse::error('Feedback not found', null, 404);
            }

            // Mark as read if new
            if ($feedback->status === 'new') {
                $this->feedbackService->markAsRead($feedback->id);
                $feedback->refresh();
            }

            return ApiResponse::success(
                new FeedbackResource($feedback),
                'Feedback retrieved successfully'
            );
        } catch (\Exception $e) {
            Log::error('Failed to retrieve feedback', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to retrieve feedback', null, 500);
        }
    }

    /**
     * Get feedback statistics
     */
    public function statistics(): JsonResponse
    {
        try {
            $stats = $this->feedbackService->getStatistics();
            $typeStats = $this->feedbackService->getTypeStatistics();
            $priorityStats = $this->feedbackService->getPriorityStatistics();

            return ApiResponse::success([
                'general' => $stats,
                'by_type' => $typeStats,
                'by_priority' => $priorityStats,
            ], 'Statistics retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Failed to retrieve feedback statistics', [
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to retrieve statistics', null, 500);
        }
    }

    /**
     * Reply to feedback
     */
    public function reply(string $uuid, ReplyFeedbackRequest $request): JsonResponse
    {
        try {
            $feedback = $this->feedbackService->findByUuid($uuid);

            if (!$feedback) {
                return ApiResponse::error('Feedback not found', null, 404);
            }

            $replyMessage = $request->input('reply_message');
            $replySubject = $request->input('reply_subject', 'Re: ' . $feedback->subject);
            $markAsReplied = $request->input('mark_as_replied', true);

            // Mark feedback as replied
            if ($markAsReplied) {
                $this->feedbackService->markAsReplied(
                    $feedback->id,
                    $replyMessage,
                    $request->user()->id
                );
            }

            // Dispatch reply email job to queue (non-blocking)
            SendFeedbackReplyEmailJob::dispatch(
                $feedback,
                $replyMessage,
                $replySubject,
                $request->user()->name,
                $request->user()->email
            );

            $feedback->refresh();

            Log::info('Feedback reply sent successfully', [
                'feedback_uuid' => $uuid,
                'replied_by' => $request->user()->id,
            ]);

            return ApiResponse::success(
                new FeedbackResource($feedback),
                'Reply sent successfully'
            );
        } catch (\Exception $e) {
            Log::error('Failed to send feedback reply', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error('Failed to send reply', null, 500);
        }
    }

    /**
     * Mark feedback as read
     */
    public function markAsRead(string $uuid): JsonResponse
    {
        try {
            $feedback = $this->feedbackService->findByUuid($uuid);

            if (!$feedback) {
                return ApiResponse::error('Feedback not found', null, 404);
            }

            $this->feedbackService->markAsRead($feedback->id);
            $feedback->refresh();

            return ApiResponse::success(
                new FeedbackResource($feedback),
                'Feedback marked as read'
            );
        } catch (\Exception $e) {
            Log::error('Failed to mark feedback as read', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to mark feedback as read', null, 500);
        }
    }

    /**
     * Mark feedback as closed
     */
    public function markAsClosed(string $uuid): JsonResponse
    {
        try {
            $feedback = $this->feedbackService->findByUuid($uuid);

            if (!$feedback) {
                return ApiResponse::error('Feedback not found', null, 404);
            }

            $this->feedbackService->update($feedback->id, ['status' => 'closed']);
            $feedback->refresh();

            return ApiResponse::success(
                new FeedbackResource($feedback),
                'Feedback marked as closed'
            );
        } catch (\Exception $e) {
            Log::error('Failed to mark feedback as closed', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to mark feedback as closed', null, 500);
        }
    }

    /**
     * Delete feedback
     */
    public function destroy(string $uuid): JsonResponse
    {
        try {
            $feedback = $this->feedbackService->findByUuid($uuid);

            if (!$feedback) {
                return ApiResponse::error('Feedback not found', null, 404);
            }

            $this->feedbackService->delete($feedback->id);

            Log::info('Feedback deleted successfully', [
                'feedback_uuid' => $uuid,
                'deleted_by' => Auth::id(),
            ]);

            return ApiResponse::success(null, 'Feedback deleted successfully');
        } catch (\Exception $e) {
            Log::error('Failed to delete feedback', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to delete feedback', null, 500);
        }
    }

    /**
     * Get all comments for a feedback
     */
    public function getComments(string $uuid): JsonResponse
    {
        try {
            $feedback = $this->feedbackService->findByUuid($uuid);

            if (!$feedback) {
                return ApiResponse::error('Feedback not found', null, 404);
            }

            $comments = $this->feedbackService->getFeedbackComments($feedback, true);

            return ApiResponse::success(
                CommentResource::collection($comments),
                'Comments retrieved successfully'
            );
        } catch (\Exception $e) {
            Log::error('Failed to retrieve feedback comments', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to retrieve comments', null, 500);
        }
    }

    /**
     * Add a comment to feedback
     * Controller: Handle HTTP only (validation, authorization, response)
     * Business logic is in FeedbackService
     */
    public function addComment(string $uuid, StoreFeedbackCommentRequest $request): JsonResponse
    {
        try {
            $feedback = $this->feedbackService->findByUuid($uuid);

            if (!$feedback) {
                return ApiResponse::error('Feedback not found', null, 404);
            }

            // Get validated data (includes user_id from Auth)
            $commentData = $request->getValidatedData();

            // Service handles business logic: create comment, send email, clear cache
            $comment = $this->feedbackService->addCommentToFeedback($feedback, $commentData);

            Log::info('Comment added to feedback successfully', [
                'feedback_uuid' => $uuid,
                'comment_id' => $comment->id,
                'user_id' => $request->user()->id,
            ]);

            return ApiResponse::success(
                new CommentResource($comment),
                'Comment added successfully',
                201
            );
        } catch (\Exception $e) {
            Log::error('Failed to add comment to feedback', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error('Failed to add comment', null, 500);
        }
    }
}
