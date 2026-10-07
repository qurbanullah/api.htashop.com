<?php

namespace App\Http\Controllers\V1\Purchasing;

use App\Http\Controllers\Controller;
use App\Http\Responses\V1\ApiResponse;
use App\Services\Purchasing\SupplierOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierOrderController extends Controller
{
    public function __construct(
        protected SupplierOrderService $service,
    ) {}

    /**
     * The supplier purchase list — open import-on-demand orders rolled up by SKU.
     */
    public function index(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->service->aggregateOnDemand($request->all()),
            'Supplier purchase list retrieved successfully'
        );
    }
}
