<?php

namespace App\Http\Controllers\V1\Warehouse;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Warehouse\StoreWarehouseRequest;
use App\Http\Requests\V1\Warehouse\UpdateWarehouseRequest;
use App\Http\Resources\V1\Warehouse\WarehouseResource;
use App\Http\Responses\V1\ApiResponse;
use App\Models\Warehouse;
use App\Services\Warehouse\WarehouseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WarehouseController extends Controller
{
    public function __construct(protected WarehouseService $warehouseService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Warehouse::class);
        return ApiResponse::success(
            WarehouseResource::collection($this->warehouseService->read($request->all())),
            'Warehouses retrieved successfully'
        );
    }

    public function store(StoreWarehouseRequest $request): JsonResponse
    {
        $this->authorize('create', Warehouse::class);
        $warehouse = $this->warehouseService->create($request->validated());
        return ApiResponse::success(new WarehouseResource($warehouse), 'Warehouse created successfully', 201);
    }

    public function show(string $uuid): JsonResponse
    {
        $warehouse = $this->warehouseService->searchByUuid($uuid);
        $this->authorize('view', $warehouse);
        return ApiResponse::success(new WarehouseResource($warehouse), 'Warehouse retrieved successfully');
    }

    public function update(UpdateWarehouseRequest $request, string $uuid): JsonResponse
    {
        $warehouse = $this->warehouseService->searchByUuid($uuid);
        $this->authorize('update', $warehouse);
        $warehouse = $this->warehouseService->update($warehouse, $request->validated());
        return ApiResponse::success(new WarehouseResource($warehouse), 'Warehouse updated successfully');
    }

    public function destroy(string $uuid): JsonResponse
    {
        $warehouse = $this->warehouseService->searchByUuid($uuid);
        $this->authorize('delete', $warehouse);
        $this->warehouseService->delete($warehouse);
        return ApiResponse::success(null, 'Warehouse deleted successfully');
    }
}
