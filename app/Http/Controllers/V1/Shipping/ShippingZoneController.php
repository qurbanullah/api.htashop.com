<?php

namespace App\Http\Controllers\V1\Shipping;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Shipping\StoreShippingZoneRequest;
use App\Http\Requests\V1\Shipping\UpdateShippingZoneRequest;
use App\Http\Resources\V1\Shipping\ShippingZoneResource;
use App\Http\Responses\V1\ApiResponse;
use App\Models\ShippingZone;
use App\Services\Shipping\ShippingZoneService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShippingZoneController extends Controller
{
    public function __construct(protected ShippingZoneService $zones) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ShippingZone::class);

        return ApiResponse::success(
            ShippingZoneResource::collection($this->zones->read($request->all())),
            'Shipping zones retrieved successfully',
        );
    }

    public function store(StoreShippingZoneRequest $request): JsonResponse
    {
        $this->authorize('create', ShippingZone::class);

        $zone = $this->zones->create($request->validated());

        return ApiResponse::success(
            new ShippingZoneResource($zone),
            'Shipping zone created successfully',
            201,
        );
    }

    public function show(string $uuid): JsonResponse
    {
        $zone = $this->zones->searchByUuid($uuid);
        $this->authorize('view', $zone);

        return ApiResponse::success(
            new ShippingZoneResource($zone),
            'Shipping zone retrieved successfully',
        );
    }

    public function update(UpdateShippingZoneRequest $request, string $uuid): JsonResponse
    {
        $zone = $this->zones->searchByUuid($uuid);
        $this->authorize('update', $zone);

        $zone = $this->zones->update($zone, $request->validated());

        return ApiResponse::success(
            new ShippingZoneResource($zone),
            'Shipping zone updated successfully',
        );
    }

    public function destroy(string $uuid): JsonResponse
    {
        $zone = $this->zones->searchByUuid($uuid);
        $this->authorize('delete', $zone);

        $this->zones->delete($zone);

        return ApiResponse::success(null, 'Shipping zone deleted successfully');
    }
}
