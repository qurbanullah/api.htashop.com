<?php

namespace App\Http\Controllers\V1\SellerDashboard;

use App\Http\Controllers\Controller;
use App\Http\Responses\V1\ApiResponse;
use App\Services\SellerDashboard\SellerDashboardService;
use Illuminate\Http\JsonResponse;

class SellerDashboardController extends Controller
{
    public function __construct(
        protected SellerDashboardService $service,
    ) {
    }

    public function index(): JsonResponse
    {
        return ApiResponse::success(
            $this->service->summary(),
            'Seller dashboard retrieved successfully',
        );
    }
}
