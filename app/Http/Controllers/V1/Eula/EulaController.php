<?php

namespace App\Http\Controllers\V1\Eula;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Eula\StoreEulaRequest;
use App\Http\Requests\V1\Eula\UpdateEulaRequest;
use App\Http\Resources\V1\Eula\EulaResource;
use App\Http\Responses\V1\ApiResponse;
use App\Models\Eula;
use App\Services\Eula\EulaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EulaController extends Controller
{
    public function __construct(
        protected EulaService $eulaService
    ) {
        $this->authorizeResource(Eula::class, 'eula');
    }

    /**
     * Display a listing of EULAs.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['status', 'software_id', 'version_id', 'search', 'sort', 'order']);
        $perPage = $request->input('per_page', 15);

        $eulas = $this->eulaService->list($filters, $perPage);

        return ApiResponse::success(
            EulaResource::collection($eulas)->response()->getData(true),
            'EULAs retrieved successfully'
        );
    }

    /**
     * Store a newly created EULA.
     */
    public function store(StoreEulaRequest $request): JsonResponse
    {
        $eula = $this->eulaService->create(
            $request->validated(),
            $request->user()->id
        );

        return ApiResponse::success(
            new EulaResource($eula),
            'EULA created successfully',
            201
        );
    }

    /**
     * Display the specified EULA.
     */
    public function show(Eula $eula): JsonResponse
    {
        $eula = $this->eulaService->getByUuid($eula->uuid);

        return ApiResponse::success(
            new EulaResource($eula),
            'EULA retrieved successfully'
        );
    }

    /**
     * Update the specified EULA.
     */
    public function update(UpdateEulaRequest $request, Eula $eula): JsonResponse
    {
        $updatedEula = $this->eulaService->update(
            $eula,
            $request->validated(),
            $request->user()->id
        );

        return ApiResponse::success(
            new EulaResource($updatedEula),
            'EULA updated successfully'
        );
    }

    /**
     * Remove the specified EULA.
     */
    public function destroy(Eula $eula): JsonResponse
    {
        $this->eulaService->delete($eula);

        return ApiResponse::success(
            null,
            'EULA deleted successfully'
        );
    }

    /**
     * Get EULA statistics.
     */
    public function statistics(): JsonResponse
    {
        $stats = $this->eulaService->getStatistics();

        return ApiResponse::success(
            $stats,
            'Statistics retrieved successfully'
        );
    }

    /**
     * Get the active EULA for download/consent.
     */
    public function active(Request $request): JsonResponse
    {
        $softwareId = $request->input('software_id');
        $versionId = $request->input('version_id');

        $eula = $this->eulaService->getActiveEula($softwareId, $versionId);

        if (!$eula) {
            return ApiResponse::error(
                'No active EULA found',
                null,
                404
            );
        }

        return ApiResponse::success(
            new EulaResource($eula),
            'Active EULA retrieved successfully'
        );
    }
}
