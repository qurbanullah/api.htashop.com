<?php

namespace App\Http\Controllers\V1\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Inventory\StoreInventoryRequest;
use App\Http\Requests\V1\Inventory\UpdateInventoryRequest;
use App\Http\Resources\V1\Inventory\InventoryResource;
use App\Http\Responses\V1\ApiResponse;
use App\Models\Inventory;
use App\Services\Inventory\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function __construct(protected InventoryService $inventoryService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Inventory::class);
        return ApiResponse::success(
            InventoryResource::collection($this->inventoryService->read($request->all())),
            'Inventory records retrieved successfully'
        );
    }

    public function store(StoreInventoryRequest $request): JsonResponse
    {
        $this->authorize('create', Inventory::class);
        $inventory = $this->inventoryService->upsert($request->validated());
        return ApiResponse::success(new InventoryResource($inventory), 'Inventory set successfully', 201);
    }

    public function show(string $uuid): JsonResponse
    {
        $inventory = Inventory::where('uuid', $uuid)->with(['warehouse', 'stockable'])->firstOrFail();
        $this->authorize('view', $inventory);
        return ApiResponse::success(new InventoryResource($inventory), 'Inventory retrieved successfully');
    }

    public function update(UpdateInventoryRequest $request, string $uuid): JsonResponse
    {
        $inventory = Inventory::where('uuid', $uuid)->firstOrFail();
        $this->authorize('update', $inventory);
        $inventory = $this->inventoryService->update($inventory, $request->validated());
        return ApiResponse::success(new InventoryResource($inventory), 'Inventory updated successfully');
    }

    public function destroy(string $uuid): JsonResponse
    {
        $inventory = Inventory::where('uuid', $uuid)->firstOrFail();
        $this->authorize('delete', $inventory);
        $this->inventoryService->delete($inventory);
        return ApiResponse::success(null, 'Inventory deleted successfully');
    }
}
