<?php

namespace App\Http\Controllers\V1\Measurement;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Measurement\MeasurementStoreRequest;
use App\Http\Requests\V1\Measurement\MeasurementUpdateRequest;
use App\Http\Resources\V1\Measurement\MeasurementResource;
use App\Http\Responses\V1\ApiResponse;
use App\Models\Measurement;
use App\Services\Measurement\MeasurementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeasurementController extends Controller
{
    public function __construct(protected MeasurementService $measurementService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Measurement::class);

        return ApiResponse::success(MeasurementResource::collection($this->measurementService->read($request->all())), 'Measurements retrieved successfully');
    }

    public function show(string $uuid): JsonResponse
    {
        $measurement = $this->measurementService->searchByUuid($uuid);
        $this->authorize('view', $measurement);

        return ApiResponse::success(new MeasurementResource($measurement), 'Measurement retrieved successfully');
    }

    public function store(MeasurementStoreRequest $request): JsonResponse
    {
        $this->authorize('create', Measurement::class);

        return ApiResponse::success(new MeasurementResource($this->measurementService->create($request->validated())), 'Measurement created successfully', 201);
    }

    public function update(MeasurementUpdateRequest $request, string $uuid): JsonResponse
    {
        $measurement = $this->measurementService->searchByUuid($uuid);
        $this->authorize('update', $measurement);

        return ApiResponse::success(new MeasurementResource($this->measurementService->update($measurement, $request->validated())), 'Measurement updated successfully');
    }

    public function destroy(string $uuid): JsonResponse
    {
        $measurement = $this->measurementService->searchByUuid($uuid);
        $this->authorize('delete', $measurement);
        $this->measurementService->delete($measurement);

        return ApiResponse::success(null, 'Measurement deleted successfully');
    }
}
