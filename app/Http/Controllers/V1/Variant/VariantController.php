<?php

namespace App\Http\Controllers\V1\Variant;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Variant\VariantStoreRequest;
use App\Http\Requests\V1\Variant\VariantUpdateRequest;
use App\Http\Resources\V1\Variant\VariantResource;
use App\Http\Responses\V1\ApiResponse;
use App\Models\Variant;
use App\Services\Variant\VariantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VariantController extends Controller
{
    public function __construct(
        protected VariantService $variantService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Variant::class);

        $variants = $this->variantService->read($request->all());

        return ApiResponse::success(VariantResource::collection($variants), 'Variants retrieved successfully');
    }

    public function store(VariantStoreRequest $request): JsonResponse
    {
        $this->authorize('create', Variant::class);

        $variant = $this->variantService->create($request->validated());

        return ApiResponse::success(new VariantResource($variant), 'Variant created successfully', 201);
    }

    public function show(string $uuid): JsonResponse
    {
        $variant = $this->variantService->searchByUuid($uuid);
        $this->authorize('view', $variant);

        return ApiResponse::success(new VariantResource($variant), 'Variant retrieved successfully');
    }

    public function update(VariantUpdateRequest $request, string $uuid): JsonResponse
    {
        $variant = $this->variantService->searchByUuid($uuid);
        $this->authorize('update', $variant);

        $variant = $this->variantService->update($variant, $request->validated());

        return ApiResponse::success(new VariantResource($variant), 'Variant updated successfully');
    }

    public function destroy(string $uuid): JsonResponse
    {
        $variant = $this->variantService->searchByUuid($uuid);
        $this->authorize('delete', $variant);

        $this->variantService->delete($variant);

        return ApiResponse::success(null, 'Variant deleted successfully');
    }
}