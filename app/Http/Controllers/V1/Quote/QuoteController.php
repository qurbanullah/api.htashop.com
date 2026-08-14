<?php

namespace App\Http\Controllers\V1\Quote;

use App\Http\Controllers\Controller;
use App\Services\Quotes\QuoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class QuoteController extends Controller
{
    protected QuoteService $quoteService;

    public function __construct(QuoteService $quoteService)
    {
        $this->quoteService = $quoteService;
    }

    /**
     * Submit a new quote request.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function submitQuoteRequest(\App\Http\Requests\V1\Quote\SubmitQuoteRequestRequest $request): JsonResponse
    {
        

        try {
            $quoteRequest = $this->quoteService->createQuoteRequest($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Quote request submitted successfully. We will get back to you soon.',
                'data' => [
                    'uuid' => $quoteRequest->uuid,
                    'reference_number' => 'QR-' . $quoteRequest->id,
                ]
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit quote request. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get a quote request by UUID (for tracking).
     *
     * @param string $uuid
     * @return JsonResponse
     */
    public function getQuoteRequest(string $uuid): JsonResponse
    {
        try {
            $quoteRequest = $this->quoteService->getQuoteRequestByUuid($uuid);

            if (!$quoteRequest) {
                return response()->json([
                    'success' => false,
                    'message' => 'Quote request not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'uuid' => $quoteRequest->uuid,
                    'reference_number' => 'QR-' . $quoteRequest->id,
                    'status' => $quoteRequest->status,
                    'created_at' => $quoteRequest->created_at->format('Y-m-d H:i:s'),
                    'quoted_at' => $quoteRequest->quoted_at?->format('Y-m-d H:i:s'),
                    'has_response' => $quoteRequest->responses->isNotEmpty(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve quote request',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Track quote response view.
     *
     * @param string $responseUuid
     * @return JsonResponse
     */
    public function trackView(string $responseUuid): JsonResponse
    {
        try {
            $response = \App\Models\QuoteResponse::where('uuid', $responseUuid)->first();

            if (!$response) {
                return response()->json([
                    'success' => false,
                    'message' => 'Quote response not found'
                ], 404);
            }

            $response->trackView();

            return response()->json([
                'success' => true,
                'message' => 'View tracked successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to track view',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}
