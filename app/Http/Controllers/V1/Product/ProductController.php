<?php

namespace App\Http\Controllers\V1\Product;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Product\ProductIndexRequest;
use App\Http\Requests\V1\Product\ProductStoreRequest;
use App\Http\Requests\V1\Product\ProductUpdateRequest;
use App\Http\Resources\V1\Product\ProductResource;
use App\Http\Responses\V1\ApiResponse;
use App\Models\Product;
use App\Services\Product\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class ProductController extends Controller
{
    public function __construct(
        protected ProductService $productService,
    ) {
    }

    public function index(ProductIndexRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Product::class);

        $products = $this->productService->read($request->validated());

        return ApiResponse::success(ProductResource::collection($products), 'Products retrieved successfully');
    }

    public function store(ProductStoreRequest $request): JsonResponse
    {
        $this->authorize('create', Product::class);

        $product = $this->productService->create($request->validated());

        return ApiResponse::success(new ProductResource($product), 'Product created successfully', 201);
    }

    public function show(string $routeKey): JsonResponse|RedirectResponse
    {
        $product = $this->productService->searchByUuid($routeKey);
        $this->authorize('view', $product);

        // Enforce the canonical {slug}-{uuid8} URL — stale slugs 301 to the canonical one.
        $canonical = $product->slug . '-' . substr($product->uuid, 0, 8);
        if (! $this->isUuid($routeKey) && $routeKey !== $canonical) {
            return redirect()->to(url('api/v1/products/' . $canonical), 301);
        }

        return ApiResponse::success(new ProductResource($product), 'Product retrieved successfully');
    }

    private function isUuid(string $key): bool
    {
        return preg_match('/^[0-9a-fA-F-]{36}$/', $key) === 1;
    }

    public function update(ProductUpdateRequest $request, string $uuid): JsonResponse
    {
        $product = $this->productService->searchByUuid($uuid);
        $this->authorize('update', $product);

        $product = $this->productService->update($product, $request->validated());

        return ApiResponse::success(new ProductResource($product), 'Product updated successfully');
    }

    public function destroy(string $uuid): JsonResponse
    {
        $product = $this->productService->searchByUuid($uuid);
        $this->authorize('delete', $product);

        $this->productService->delete($product);

        return ApiResponse::success(null, 'Product deleted successfully');
    }
}
