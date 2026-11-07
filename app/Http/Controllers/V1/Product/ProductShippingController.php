<?php

namespace App\Http\Controllers\V1\Product;

use App\Helpers\CacheHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Product\UpdateProductShippingRequest;
use App\Http\Resources\V1\Product\ProductShippingResource;
use App\Http\Responses\V1\ApiResponse;
use App\Models\Product;
use App\Models\ProductShipping;
use App\Services\Product\ProductService;
use Illuminate\Http\JsonResponse;

class ProductShippingController extends Controller
{
    public function __construct(
        protected ProductService $productService,
    ) {}

    public function show(string $uuid): JsonResponse
    {
        $product = $this->productService->searchByUuid($uuid);
        $this->authorize('view', $product);

        $shipping = $this->resolveShipping($product);

        return ApiResponse::success(
            $shipping ? new ProductShippingResource($shipping) : null,
            'Product shipping retrieved successfully',
        );
    }

    public function update(UpdateProductShippingRequest $request, string $uuid): JsonResponse
    {
        $product = $this->productService->searchByUuid($uuid);
        $this->authorize('update', $product);

        $shipping = ProductShipping::query()->firstOrNew(['product_id' => $product->id]);

        $shipping->fill($request->validated());
        $shipping->product_id = $product->id;
        $shipping->save();

        // `ProductService::searchByUuid()` caches the product together with its
        // `shipping` relation for ten minutes, so a write here has to evict it
        // or the show endpoint would keep serving the previous packaging data.
        CacheHelper::forget([], "products:{$product->uuid}");

        return ApiResponse::success(
            new ProductShippingResource($this->resolveShipping($product->fresh())),
            'Product shipping saved successfully',
        );
    }

    private function resolveShipping(Product $product): ?ProductShipping
    {
        $shipping = $product->shipping;

        if (! $shipping instanceof ProductShipping) {
            return null;
        }

        return $shipping->loadMissing([
            'weightUnit',
            'dimensionUnit',
            'packagingType',
            'hazardClass',
            'packingGroup',
        ]);
    }
}
