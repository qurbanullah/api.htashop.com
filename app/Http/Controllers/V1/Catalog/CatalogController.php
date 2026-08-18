<?php

namespace App\Http\Controllers\V1\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Catalog\CatalogProductDetailResource;
use App\Http\Resources\V1\Catalog\CatalogProductResource;
use App\Http\Responses\V1\ApiResponse;
use App\Models\Category;
use App\Services\Catalog\CatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function __construct(
        protected CatalogService $catalogService,
    ) {
    }

    public function products(Request $request): JsonResponse
    {
        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'category_ids' => $this->arrayParam($request, 'category_ids'),
            'brand_ids' => $this->arrayParam($request, 'brand_ids'),
            'feature_ids' => $this->arrayParam($request, 'feature_ids'),
            'min_price' => $request->input('min_price'),
            'max_price' => $request->input('max_price'),
            'sort' => $request->string('sort', 'newest')->toString(),
            'per_page' => (int) $request->integer('per_page', 12),
        ];

        $products = $this->catalogService->products($filters);

        return ApiResponse::success(
            [
                'data' => CatalogProductResource::collection($products->items())->resolve(),
                'meta' => [
                    'current_page' => $products->currentPage(),
                    'last_page' => $products->lastPage(),
                    'per_page' => $products->perPage(),
                    'total' => $products->total(),
                    'from' => $products->firstItem(),
                    'to' => $products->lastItem(),
                ],
            ],
            'Products retrieved successfully',
        );
    }

    public function filters(): JsonResponse
    {
        return ApiResponse::success(
            $this->catalogService->filters(),
            'Catalog filters retrieved successfully',
        );
    }

    public function topNav(): JsonResponse
    {
        $categories = Category::query()
            ->where('is_active', true)
            ->whereNull('parent_id')
            ->whereRaw('JSON_EXTRACT(metadata, "$.show_top_category_nav") = true')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->limit((int) config('catalog.top_nav.limit', 8))
            ->get(['id', 'name', 'slug']);

        return ApiResponse::success(
            $categories,
            'Top navigation categories retrieved successfully',
        );
    }

    public function show(string $key): JsonResponse
    {
        $product = $this->catalogService->show($key);

        return ApiResponse::success(
            new CatalogProductDetailResource($product),
            'Product retrieved successfully',
        );
    }

    private function arrayParam(Request $request, string $key): array
    {
        $value = $request->input($key);

        if (is_array($value)) {
            return array_values(array_filter(array_map('intval', $value)));
        }

        if (is_string($value) && trim($value) !== '') {
            return array_values(array_filter(array_map('intval', explode(',', $value))));
        }

        return [];
    }
}
