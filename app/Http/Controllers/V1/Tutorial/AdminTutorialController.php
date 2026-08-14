<?php

declare(strict_types=1);

namespace App\Http\Controllers\V1\Tutorial;

use App\Enums\TutorialTypeEnum;
use App\Enums\TutorialStatusEnum;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Tutorial;
use App\Http\Requests\V1\Tutorial\TutorialStoreRequest;
use App\Http\Requests\V1\Tutorial\TutorialUpdateRequest;
use App\Http\Resources\V1\Tutorial\TutorialResource;
use App\Services\Tutorial\TutorialService;

/**
 * AdminTutorialController - Handles admin operations for tutorials
 *
 * This controller follows SOLID principles:
 * - Single Responsibility: Only handles HTTP layer for tutorials
 * - Open/Closed: Extendable through service layer
 * - Dependency Inversion: Depends on TutorialService abstraction
 */
class AdminTutorialController extends Controller
{
    public function __construct(
        protected TutorialService $tutorialService
    ) {}

    /**
     * List all tutorials (admin view with filters)
     * PROTECTED endpoint - requires authentication
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = [
                'search' => $request->input('search'),
                'status' => $request->input('status'),
                'type' => $request->input('type'),
                'difficulty_level' => $request->input('difficulty_level'),
                'date_from' => $request->input('date_from'),
                'date_to' => $request->input('date_to'),
            ];

            $filters = array_filter($filters);
            $perPage = (int) $request->input('per_page', 15);

            $tutorials = $this->tutorialService->getAllTutorials($filters, $perPage);

            return response()->json([
                'success' => true,
                'data' => TutorialResource::collection($tutorials->items()),
                'meta' => [
                    'current_page' => $tutorials->currentPage(),
                    'per_page' => $tutorials->perPage(),
                    'total' => $tutorials->total(),
                    'last_page' => $tutorials->lastPage(),
                    'from' => $tutorials->firstItem(),
                    'to' => $tutorials->lastItem(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve tutorials',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get tutorial statistics
     * PROTECTED endpoint
     */
    public function stats(Request $request): JsonResponse
    {
        try {
            $stats = $this->tutorialService->getTutorialStats();

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
     * Get available tutorial types
     * PROTECTED endpoint
     */
    public function types(): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => TutorialTypeEnum::options(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve tutorial types',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get available tutorial statuses
     * PROTECTED endpoint
     */
    public function statuses(): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => TutorialStatusEnum::options(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve tutorial statuses',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display a specific tutorial
     * PROTECTED endpoint
     */
    public function show(string $uuid): JsonResponse
    {
        try {
            $tutorial = $this->tutorialService->getTutorialByUuid($uuid);

            if (!$tutorial) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tutorial not found',
                ], 404);
            }

            $this->authorize('view', $tutorial);

            return response()->json([
                'success' => true,
                'data' => new TutorialResource($tutorial),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve tutorial',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created tutorial
     * PROTECTED endpoint
     */
    public function store(TutorialStoreRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', Tutorial::class);

            $data = $request->validated();
            $data['created_by'] = auth()->id();

            $tutorial = $this->tutorialService->createTutorial($data);

            return response()->json([
                'success' => true,
                'message' => 'Tutorial created successfully',
                'data' => new TutorialResource($tutorial),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create tutorial',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update an existing tutorial
     * PROTECTED endpoint
     */
    public function update(TutorialUpdateRequest $request, string $uuid): JsonResponse
    {
        try {
            $tutorial = $this->tutorialService->getTutorialByUuid($uuid);

            if (!$tutorial) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tutorial not found',
                ], 404);
            }

            $this->authorize('update', $tutorial);

            $data = $request->validated();
            $updatedTutorial = $this->tutorialService->updateTutorial($tutorial, $data);

            return response()->json([
                'success' => true,
                'message' => 'Tutorial updated successfully',
                'data' => new TutorialResource($updatedTutorial),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update tutorial',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a tutorial
     * PROTECTED endpoint
     */
    public function destroy(string $uuid): JsonResponse
    {
        try {
            $tutorial = $this->tutorialService->getTutorialByUuid($uuid);

            if (!$tutorial) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tutorial not found',
                ], 404);
            }

            $this->authorize('delete', $tutorial);

            $this->tutorialService->deleteTutorial($tutorial);

            return response()->json([
                'success' => true,
                'message' => 'Tutorial deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete tutorial',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get popular tutorials
     * PROTECTED endpoint
     */
    public function popular(Request $request): JsonResponse
    {
        try {
            $limit = (int) $request->input('limit', 10);
            $tutorials = $this->tutorialService->getPopularTutorials($limit);

            return response()->json([
                'success' => true,
                'data' => TutorialResource::collection($tutorials),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve popular tutorials',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
