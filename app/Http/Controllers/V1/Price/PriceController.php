<?php

namespace App\Http\Controllers\V1\Price;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Price\PriceStoreRequest;
use App\Http\Requests\V1\Price\PriceUpdateRequest;
use App\Http\Resources\V1\Price\PriceResource;
use App\Http\Responses\V1\ApiResponse;
use App\Models\Price;
use App\Services\Price\PriceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PriceController extends Controller
{
    public function __construct(protected PriceService $priceService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Price::class);
        return ApiResponse::success(PriceResource::collection($this->priceService->read($request->all())), 'Prices retrieved successfully');
    }

    public function store(PriceStoreRequest $request): JsonResponse
    {
        $this->authorize('create', Price::class);
        return ApiResponse::success(new PriceResource($this->priceService->create($request->validated())), 'Price created successfully', 201);
    }

    public function show(int $id): JsonResponse
    {
        $price = $this->priceService->searchById($id);
        $this->authorize('view', $price);
        return ApiResponse::success(new PriceResource($price), 'Price retrieved successfully');
    }

    public function update(PriceUpdateRequest $request, int $id): JsonResponse
    {
        $price = $this->priceService->searchById($id);
        $this->authorize('update', $price);
        return ApiResponse::success(new PriceResource($this->priceService->update($price, $request->validated())), 'Price updated successfully');
    }

    public function destroy(int $id): JsonResponse
    {
        $price = $this->priceService->searchById($id);
        $this->authorize('delete', $price);
        $this->priceService->delete($price);
        return ApiResponse::success(null, 'Price deleted successfully');
    }
}
