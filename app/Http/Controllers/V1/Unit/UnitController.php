<?php

namespace App\Http\Controllers\V1\Unit;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Unit\UnitStoreRequest;
use App\Http\Requests\V1\Unit\UnitUpdateRequest;
use App\Http\Resources\V1\Unit\UnitResource;
use App\Http\Responses\V1\ApiResponse;
use App\Models\Unit;
use App\Services\Unit\UnitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UnitController extends Controller
{
    public function __construct(protected UnitService $unitService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Unit::class);

        return ApiResponse::success(UnitResource::collection($this->unitService->read($request->all())), 'Units retrieved successfully');
    }

    public function show(string $uuid): JsonResponse
    {
        $unit = $this->unitService->searchByUuid($uuid);
        $this->authorize('view', $unit);

        return ApiResponse::success(new UnitResource($unit), 'Unit retrieved successfully');
    }

    public function store(UnitStoreRequest $request): JsonResponse
    {
        $this->authorize('create', Unit::class);

        return ApiResponse::success(new UnitResource($this->unitService->create($request->validated())->load('measurement')), 'Unit created successfully', 201);
    }

    public function update(UnitUpdateRequest $request, string $uuid): JsonResponse
    {
        $unit = $this->unitService->searchByUuid($uuid);
        $this->authorize('update', $unit);

        return ApiResponse::success(new UnitResource($this->unitService->update($unit, $request->validated())), 'Unit updated successfully');
    }

    public function destroy(string $uuid): JsonResponse
    {
        $unit = $this->unitService->searchByUuid($uuid);
        $this->authorize('delete', $unit);
        $this->unitService->delete($unit);

        return ApiResponse::success(null, 'Unit deleted successfully');
    }
}
