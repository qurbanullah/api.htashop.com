<?php

namespace App\Http\Controllers\V1\Feedback;

use App\Enums\FeedbackPriorityEnum;
use App\Enums\FeedbackTypeEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Feedback\StoreFeedbackRequest;
use App\Http\Resources\V1\Feedback\FeedbackResource;
use App\Http\Responses\V1\ApiResponse;
use App\Jobs\Feedback\SendFeedbackSubmittedEmailJob;
use App\Services\Feedbacks\FeedbackService;
use App\Support\Tenant\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class FeedbackController extends Controller
{
    protected FeedbackService $feedbackService;

    public function __construct(FeedbackService $feedbackService)
    {
        $this->feedbackService = $feedbackService;
    }

    /**
     * Submit new feedback via API
     */
    public function submitFeedback(StoreFeedbackRequest $request): JsonResponse
    {
        try {
            // Get validated data with defaults
            $data = $request->getValidatedData();

            // Resolve the storefront tenant and optional authenticated user
            $data['tenant_id'] = TenantContext::publicTenantId($request);
            $data['user_id'] = $request->user('api')?->id;

            // Create the feedback record
            $feedback = $this->feedbackService->create($data);

            // Dispatch email job to queue (non-blocking)
            SendFeedbackSubmittedEmailJob::dispatch($feedback);

            // Log the feedback submission for audit purposes
            Log::info('API Feedback submitted successfully', [
                'feedback_id' => $feedback->id,
                'type' => $feedback->type,
                'email' => $feedback->email,
                'ip_address' => $feedback->ip_address,
                'created_at' => $feedback->created_at,
            ]);

            // Return success response with feedback data
            return ApiResponse::success(
                new FeedbackResource($feedback),
                'Feedback submitted successfully! We will review your feedback and get back to you if needed.',
                201
            );

        } catch (\Exception $e) {
            // Log the error for debugging
            Log::error('Failed to submit API feedback', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all(),
                'ip_address' => $request->ip(),
            ]);

            // Return error response
            return ApiResponse::error('Failed to submit feedback. Please try again later.', null, 500);
        }
    }

    /**
     * Get feedback by reference UUID (for checking status)
     */
    public function getFeedback(string $reference): JsonResponse
    {
        try {
            $feedback = $this->feedbackService->findByUuid($reference);

            if (!$feedback) {
                return ApiResponse::error('Feedback not found.', null, 404);
            }

            return ApiResponse::success(new FeedbackResource($feedback));
        } catch (\Exception $e) {
            Log::error('Failed to retrieve API feedback', [
                'reference' => $reference,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error('Feedback not found.', null, 404);
        }
    }

    /**
     * Get available feedback types and options
     */
    public function getOptions(): JsonResponse
    {
        $feedbackTypes = [];
        foreach (FeedbackTypeEnum::cases() as $type) {
            $feedbackTypes[$type->value] = $type->label();
        }

        $priorityLevels = [];
        foreach (FeedbackPriorityEnum::cases() as $priority) {
            $priorityLevels[$priority->value] = $priority->label();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'feedback_types' => $feedbackTypes,
                'priority_levels' => $priorityLevels,
                'validation_rules' => [
                    'name' => 'required|string|max:255',
                    'email' => 'required|email|max:255',
                    'subject' => 'required|string|max:255',
                    'message' => 'required|string|min:10|max:5000',
                    'type' => 'required|in:feedback,feature_request,suggestion,bug_report',
                    'priority' => 'optional|in:low,medium,high,critical',
                    'page_url' => 'optional|string|max:2048',
                ],
            ],
        ]);
    }
}
