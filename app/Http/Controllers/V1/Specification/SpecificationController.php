<?php

namespace App\Http\Controllers\V1\Specification;

use App\Http\Controllers\Controller;
use App\Models\Specification;
use App\Services\SpecificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SpecificationController extends Controller
{
    public function __construct(
        protected SpecificationService $specificationService
    ) {}

    /**
     * Get popular specifications
     * GET /api/v1/specifications/popular
     */
    public function popular(\App\Http\Requests\V1\Specification\Popular\PopularRequest $request): JsonResponse
    {
        

        $type = $request->get('type', 'product');
        $limit = $request->get('limit', 20);

        $specifications = $this->specificationService->getPopularSpecifications($type, $limit);

        return response()->json([
            'success' => true,
            'data' => $specifications
        ]);
    }

    /**
     * Search specifications
     * GET /api/v1/specifications/search?q=keyword
     */
    public function search(\App\Http\Requests\V1\Specification\Search\SearchRequest $request): JsonResponse
    {
        

        $query = $request->get('q');
        $type = $request->get('type', 'product');
        $limit = $request->get('limit', 20);

        $specifications = $this->specificationService->searchSpecifications($query, $type, $limit);

        return response()->json([
            'success' => true,
            'data' => $specifications
        ]);
    }

    /**
     * Get a specific specification by type and slug
     * GET /api/v1/specifications/{type}/{slug}
     */
    public function show(string $type, string $slug): JsonResponse
    {
        $specification = $this->specificationService->findBySlug($slug, $type);

        if (!$specification) {
            return response()->json([
                'success' => false,
                'message' => 'Specification not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $specification->load('products')
        ]);
    }

    /**
     * Create a new specification (admin only)
     * POST /api/v1/specifications
     */
    public function store(Request $request): JsonResponse
    {
        

        try {
            $specification = $this->specificationService->createSpecification($validator->validated());

            return response()->json([
                'success' => true,
                'message' => 'Specification created successfully',
                'data' => $specification
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create specification: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a specification (admin only)
     * DELETE /api/v1/specifications/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $specification = Specification::findOrFail($id);

            // Check if specification is in use
            if ($specification->usage_count > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete specification that is currently in use'
                ], 400);
            }

            $specification->delete();

            return response()->json([
                'success' => true,
                'message' => 'Specification deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete specification: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete all unused specifications (admin only)
     * DELETE /api/v1/specifications/cleanup
     */
    public function cleanup(Request $request): JsonResponse
    {
        

        try {
            $type = $request->get('type', 'product');
            $deleted = $this->specificationService->deleteUnusedSpecifications($type);

            return response()->json([
                'success' => true,
                'message' => "Deleted {$deleted} unused specifications"
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to cleanup specifications: ' . $e->getMessage()
            ], 500);
        }
    }
}
