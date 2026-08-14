<?php

declare(strict_types=1);

namespace App\Http\Controllers\V1\Package;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Package\PackageResource;
use App\Http\Responses\V1\ApiResponse;
use App\Services\Packages\PackageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PackageController extends Controller
{
    public function __construct(
        protected PackageService $packageService
    ) {}

    /**
     * Get all active packages for dropdown.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $packages = $this->packageService->read(['filters' => ['is_active' => true]]);

            return ApiResponse::success(
                PackageResource::collection($packages),
                'Packages retrieved successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve packages', null, 500);
        }
    }
}
