<?php

namespace App\Http\Controllers\V1\Specification;

use App\Http\Controllers\Controller;
use App\Http\Responses\V1\ApiResponse;
use App\Models\Specification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SpecificationController extends Controller
{
    /**
     * List the curated specification keys used by the product editor.
     *
     * Specifications are a shared vocabulary, so the full catalogue is returned
     * grouped by category (`group`) and ordered for display.
     *
     * GET /api/v1/specifications?type=product
     */
    public function index(Request $request): JsonResponse
    {
        $type = $request->string('type')->toString() ?: 'product';

        $specifications = Specification::query()
            ->with('unitOfMeasure:id,code,symbol,name')
            ->where('type', $type)
            ->orderBy('group')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'group', 'unit', 'unit_id', 'description']);

        return ApiResponse::success($specifications, 'Specifications retrieved successfully');
    }
}
