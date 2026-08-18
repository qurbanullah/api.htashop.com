<?php

namespace App\Http\Controllers\V1\Highlight;

use App\Http\Controllers\Controller;
use App\Http\Responses\V1\ApiResponse;
use App\Services\Highlight\HighlightService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductHighlightController extends Controller
{
    public function __construct(
        protected HighlightService $highlightService,
    ) {
    }

    public function show(string $uuid): JsonResponse
    {
        return ApiResponse::success(
            ['highlights' => $this->highlightService->productHighlights($uuid)],
            'Product highlights retrieved successfully',
        );
    }

    public function sync(Request $request, string $uuid): JsonResponse
    {
        $data = $request->validate([
            'highlights' => ['nullable', 'array'],
            'highlights.*.highlight_id' => ['required', 'integer', 'exists:highlights,id'],
            'highlights.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'highlights.*.heading_override' => ['nullable', 'string', 'max:255'],
            'highlights.*.body_override' => ['nullable', 'string'],
        ]);

        return ApiResponse::success(
            ['highlights' => $this->highlightService->syncProduct($uuid, $data['highlights'] ?? [])],
            'Product highlights updated successfully',
        );
    }
}
