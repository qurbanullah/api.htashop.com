<?php

namespace App\Http\Controllers\V1\Feedback;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Feedback\StoreFeedbackRequest;
use App\Http\Resources\V1\Feedback\FeedbackResource;
use App\Jobs\Feedback\SendFeedbackSubmittedEmailJob;
use App\Models\Feedback;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class FeedbackController extends Controller
{
    /**
     * Submit new feedback via API
     */
    public function submitFeedback(StoreFeedbackRequest $request): JsonResponse
    {
        try {
            // Get validated data with defaults
            $validatedData = $request->getValidatedData();

            // Create the feedback record
            $feedback = Feedback::create($validatedData);

            // Dispatch email job to queue (non-blocking)
            SendFeedbackSubmittedEmailJob::dispatch($feedback);

            // Log the feedback submission for audit purposes
            Log::info('API Feedback submitted successfully', [
                'feedback_id' => $feedback->id,
                'type' => $feedback->type,
                'email' => $feedback->email,
                'software_name' => $feedback->software_name,
                'ip_address' => $feedback->ip_address,
                'created_at' => $feedback->created_at,
            ]);

            // Return success response with feedback data
            return response()->json([
                'success' => true,
                'message' => 'Feedback submitted successfully! We will review your feedback and get back to you if needed.',
                'data' => new FeedbackResource($feedback),
            ], 201);

        } catch (\Exception $e) {
            // Log the error for debugging
            Log::error('Failed to submit API feedback', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all(),
                'ip_address' => $request->ip(),
            ]);

            // Return error response
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit feedback. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get feedback by ID (for checking status)
     */
    public function getFeedback(int $id): JsonResponse
    {
        try {
            $feedback = Feedback::findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => new FeedbackResource($feedback),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Feedback not found.',
            ], 404);
        }
    }

    /**
     * Get available feedback types and options
     */
    public function getOptions(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'feedback_types' => [
                    'feedback' => 'General Feedback',
                    'feature_request' => 'Feature Request',
                    'suggestion' => 'Suggestion',
                    'bug_report' => 'Bug Report',
                ],
                'priority_levels' => [
                    'low' => 'Low',
                    'medium' => 'Medium',
                    'high' => 'High',
                    'critical' => 'Critical',
                ],
                'validation_rules' => [
                    'name' => 'required|string|max:255',
                    'email' => 'required|email|max:255',
                    'subject' => 'required|string|max:255',
                    'message' => 'required|string|min:10|max:5000',
                    'type' => 'required|in:feedback,feature_request,suggestion,bug_report',
                    'priority' => 'optional|in:low,medium,high,critical',
                ],
            ],
        ]);
    }
}
