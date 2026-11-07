<?php

namespace App\Http\Controllers\V1\Shipping;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Shipping\StoreShippingRateRequest;
use App\Http\Requests\V1\Shipping\UpdateShippingRateRequest;
use App\Http\Resources\V1\Shipping\ShippingRateResource;
use App\Http\Responses\V1\ApiResponse;
use App\Models\ShippingRate;
use App\Services\Shipping\ShippingRateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShippingRateController extends Controller
{
    public function __construct(protected ShippingRateService $rates) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ShippingRate::class);

        return ApiResponse::success(
            ShippingRateResource::collection($this->rates->read($request->all())),
            'Shipping rates retrieved successfully',
        );
    }

    public function store(StoreShippingRateRequest $request): JsonResponse
    {
        $this->authorize('create', ShippingRate::class);

        $rate = $this->rates->create($request->validated());

        return ApiResponse::success(
            new ShippingRateResource($rate),
            'Shipping rate created successfully',
            201,
        );
    }

    public function show(string $uuid): JsonResponse
    {
        $rate = $this->rates->searchByUuid($uuid);
        $this->authorize('view', $rate);

        return ApiResponse::success(
            new ShippingRateResource($rate),
            'Shipping rate retrieved successfully',
        );
    }

    public function update(UpdateShippingRateRequest $request, string $uuid): JsonResponse
    {
        $rate = $this->rates->searchByUuid($uuid);
        $this->authorize('update', $rate);

        $rate = $this->rates->update($rate, $request->validated());

        return ApiResponse::success(
            new ShippingRateResource($rate),
            'Shipping rate updated successfully',
        );
    }

    public function destroy(string $uuid): JsonResponse
    {
        $rate = $this->rates->searchByUuid($uuid);
        $this->authorize('delete', $rate);

        $this->rates->delete($rate);

        return ApiResponse::success(null, 'Shipping rate deleted successfully');
    }
}
