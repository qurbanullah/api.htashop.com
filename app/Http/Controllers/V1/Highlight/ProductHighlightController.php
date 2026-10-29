<?php

namespace App\Http\Controllers\V1\Highlight;

use App\Http\Controllers\Controller;
use App\Http\Responses\V1\ApiResponse;
use App\Services\Highlight\HighlightService;
use App\Services\Product\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductHighlightController extends Controller
{
    public function __construct(
        protected HighlightService $highlightService,
        protected ProductService $productService,
    ) {}

    public function show(string $uuid): JsonResponse
    {
        $product = $this->productService->searchByUuid($uuid);
        $this->authorize('view', $product);

        return ApiResponse::success(
            ['highlights' => $this->highlightService->productHighlights($product->uuid)],
            'Product highlights retrieved successfully',
        );
    }

    public function sync(Request $request, string $uuid): JsonResponse
    {
        $product = $this->productService->searchByUuid($uuid);
        $this->authorize('update', $product);

        $data = $request->validate([
            'highlights' => ['nullable', 'array'],
            'highlights.*.highlight_id' => ['required', 'integer', 'exists:highlights,id'],
            'highlights.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'highlights.*.heading_override' => ['nullable', 'string', 'max:255'],
            'highlights.*.body_override' => ['nullable', 'string'],
        ]);

        return ApiResponse::success(
            ['highlights' => $this->highlightService->syncProduct($product->uuid, $data['highlights'] ?? [])],
            'Product highlights updated successfully',
        );
    }
}
