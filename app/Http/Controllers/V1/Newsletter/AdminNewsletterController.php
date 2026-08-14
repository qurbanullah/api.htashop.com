<?php

declare(strict_types=1);

namespace App\Http\Controllers\V1\Newsletter;

use App\Enums\NewsletterTypeEnum;
use App\Enums\NewsletterStatusEnum;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Newsletter;
use App\Http\Requests\V1\Newsletter\NewsletterStoreRequest;
use App\Http\Requests\V1\Newsletter\NewsletterUpdateRequest;
use App\Http\Resources\V1\Newsletter\NewsletterResource;
use App\Services\Newsletter\NewsletterService;

class AdminNewsletterController extends Controller
{
    public function __construct(
        protected NewsletterService $newsletterService
    ) {}

    /**
     * List all newsletters (admin view with filters)
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

            $newsletters = $this->newsletterService->getAllNewsletters($filters, $perPage);

            return response()->json([
                'success' => true,
                'data' => NewsletterResource::collection($newsletters->items()),
                'meta' => [
                    'current_page' => $newsletters->currentPage(),
                    'per_page' => $newsletters->perPage(),
                    'total' => $newsletters->total(),
                    'last_page' => $newsletters->lastPage(),
                    'from' => $newsletters->firstItem(),
                    'to' => $newsletters->lastItem(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve newsletters',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get newsletter statistics
     * PROTECTED endpoint
     */
    public function stats(Request $request): JsonResponse
    {
        try {
            $stats = $this->newsletterService->getNewsletterStats();

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
     * Get newsletter publishing trends
     * PROTECTED endpoint
     */
    public function publishingTrends(Request $request): JsonResponse
    {
        try {
            $days = (int) $request->input('days', 30);
            $trends = $this->newsletterService->getPublishingTrends($days);

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
     * Get available newsletter types
     * PROTECTED endpoint
     */
    public function types(): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => NewsletterTypeEnum::options(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve newsletter types',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get available newsletter statuses
     * PROTECTED endpoint
     */
    public function statuses(): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => NewsletterStatusEnum::options(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve newsletter statuses',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show a specific newsletter (admin view)
     * PROTECTED endpoint
     */
    public function show(Request $request, string $uuid): JsonResponse
    {
        try {
            $newsletter = $this->newsletterService->getNewsletterByUuid($uuid);

            if (!$newsletter) {
                return response()->json([
                    'success' => false,
                    'message' => 'Newsletter not found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => new NewsletterResource($newsletter),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve newsletter',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created newsletter
     * PROTECTED endpoint
     */
    public function store(NewsletterStoreRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $validated['created_by'] = $request->user()->id;

            $newsletter = $this->newsletterService->createNewsletter($validated);

            return response()->json([
                'success' => true,
                'message' => 'Newsletter created successfully',
                'data' => new NewsletterResource($newsletter),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create newsletter',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update an existing newsletter
     * PROTECTED endpoint
     */
    public function update(NewsletterUpdateRequest $request, string $uuid): JsonResponse
    {
        try {
            $newsletter = $this->newsletterService->getNewsletterByUuid($uuid);

            if (!$newsletter) {
                return response()->json([
                    'success' => false,
                    'message' => 'Newsletter not found',
                ], 404);
            }

            $validated = $request->validated();
            $newsletter = $this->newsletterService->updateNewsletter($newsletter, $validated);

            return response()->json([
                'success' => true,
                'message' => 'Newsletter updated successfully',
                'data' => new NewsletterResource($newsletter),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update newsletter',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a newsletter
     * PROTECTED endpoint
     */
    public function destroy(string $uuid): JsonResponse
    {
        try {
            $newsletter = $this->newsletterService->getNewsletterByUuid($uuid);

            if (!$newsletter) {
                return response()->json([
                    'success' => false,
                    'message' => 'Newsletter not found',
                ], 404);
            }

            $this->newsletterService->deleteNewsletter($newsletter);

            return response()->json([
                'success' => true,
                'message' => 'Newsletter deleted successfully',
            ]);
        } catch (\Exception $e) {
            // For business logic validation errors (like cannot delete sent newsletters),
            // return 200 with success: false to avoid console errors
            if (str_contains($e->getMessage(), 'Cannot delete')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to delete newsletter',
                    'error' => $e->getMessage(),
                ], 200);
            }

            // For actual server errors, return 500
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete newsletter',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Send newsletter (action endpoint)
     * PROTECTED endpoint
     */
    public function send(Request $request, string $uuid): JsonResponse
    {
        try {
            $newsletter = $this->newsletterService->getNewsletterByUuid($uuid);

            if (!$newsletter) {
                return response()->json([
                    'success' => false,
                    'message' => 'Newsletter not found',
                ], 404);
            }

            $result = $this->newsletterService->sendNewsletter($newsletter);

            return response()->json([
                'success' => true,
                'message' => 'Newsletter sent successfully',
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send newsletter',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Schedule newsletter (action endpoint)
     * PROTECTED endpoint
     */
    public function schedule(Request $request, string $uuid): JsonResponse
    {
        try {
            $validated = $request->validate([
                'scheduled_at' => 'required|date|after:now',
            ]);

            $newsletter = $this->newsletterService->getNewsletterByUuid($uuid);

            if (!$newsletter) {
                return response()->json([
                    'success' => false,
                    'message' => 'Newsletter not found',
                ], 404);
            }

            $scheduledAt = \Carbon\Carbon::parse($validated['scheduled_at']);
            $newsletter = $this->newsletterService->scheduleNewsletter($newsletter, $scheduledAt);

            return response()->json([
                'success' => true,
                'message' => 'Newsletter scheduled successfully',
                'data' => new NewsletterResource($newsletter),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to schedule newsletter',
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
            $newsletter = $this->newsletterService->getNewsletterByUuid($uuid);

            if (!$newsletter) {
                return response()->json([
                    'success' => false,
                    'message' => 'Newsletter not found',
                ], 404);
            }

            $newsletter = $this->newsletterService->publishAsBlog($newsletter, !$newsletter->is_published_as_blog);

            return response()->json([
                'success' => true,
                'message' => $newsletter->is_published_as_blog ? 'Published as blog' : 'Unpublished from blog',
                'data' => new NewsletterResource($newsletter),
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
