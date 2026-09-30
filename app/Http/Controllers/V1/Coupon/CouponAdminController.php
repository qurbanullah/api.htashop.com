<?php

declare(strict_types=1);

namespace App\Http\Controllers\V1\Coupon;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Coupon\StoreCouponRequest;
use App\Http\Requests\V1\Coupon\UpdateCouponRequest;
use App\Http\Resources\V1\Coupon\CouponCollection;
use App\Http\Resources\V1\Coupon\CouponRedemptionResource;
use App\Http\Resources\V1\Coupon\CouponResource;
use App\Http\Responses\V1\ApiResponse;
use App\Models\Coupon;
use App\Services\Coupon\CouponAdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Admin CRUD for discount codes.
 *
 * Whether a code may be *used* is decided by `CouponService` at checkout; this
 * controller only manages the catalogue. Route model binding resolves
 * `{coupon}` by uuid (`Coupon::getRouteKeyName()`).
 */
class CouponAdminController extends Controller
{
    public function __construct(
        private CouponAdminService $coupons,
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $coupons = $this->coupons->paginate([
                'search' => $request->input('search'),
                'type' => $request->input('type'),
                'is_active' => $request->input('is_active'),
                'scope' => $request->input('scope'),
                'tenant_id' => $request->input('tenant_id'),
                'organization_id' => $request->input('organization_id'),
                'sort' => $request->input('sort'),
                'order' => $request->input('order'),
            ], $this->perPage($request));

            return ApiResponse::success(
                new CouponCollection($coupons),
                'Coupons retrieved successfully',
            );
        } catch (Throwable $throwable) {
            Log::error('Failed to list coupons', ['error' => $throwable->getMessage()]);

            return ApiResponse::error('Failed to retrieve coupons', null, 500);
        }
    }

    public function statistics(): JsonResponse
    {
        try {
            return ApiResponse::success(
                $this->coupons->statistics(),
                'Coupon statistics retrieved successfully',
            );
        } catch (Throwable $throwable) {
            Log::error('Failed to retrieve coupon statistics', ['error' => $throwable->getMessage()]);

            return ApiResponse::error('Failed to retrieve coupon statistics', null, 500);
        }
    }

    /**
     * Tenants and organizations for the coupon scope picker. Registered before
     * `{coupon}` so `scopes` is not mistaken for a coupon id.
     */
    public function scopes(): JsonResponse
    {
        try {
            return ApiResponse::success(
                $this->coupons->scopeOptions(),
                'Coupon scopes retrieved successfully',
            );
        } catch (Throwable $throwable) {
            Log::error('Failed to retrieve coupon scopes', ['error' => $throwable->getMessage()]);

            return ApiResponse::error('Failed to retrieve coupon scopes', null, 500);
        }
    }

    public function show(Coupon $coupon): JsonResponse
    {
        return ApiResponse::success(
            new CouponResource($coupon->loadCount('redemptions')),
            'Coupon retrieved successfully',
        );
    }

    public function store(StoreCouponRequest $request): JsonResponse
    {
        try {
            return ApiResponse::success(
                new CouponResource($this->coupons->create($request->validated())),
                'Coupon created successfully',
                201,
            );
        } catch (Throwable $throwable) {
            Log::error('Failed to create coupon', ['error' => $throwable->getMessage()]);

            return ApiResponse::error('Failed to create the coupon', null, 500);
        }
    }

    public function update(UpdateCouponRequest $request, Coupon $coupon): JsonResponse
    {
        try {
            return ApiResponse::success(
                new CouponResource($this->coupons->update($coupon, $request->validated())),
                'Coupon updated successfully',
            );
        } catch (Throwable $throwable) {
            Log::error('Failed to update coupon', [
                'uuid' => $coupon->uuid,
                'error' => $throwable->getMessage(),
            ]);

            return ApiResponse::error('Failed to update the coupon', null, 500);
        }
    }

    public function destroy(Coupon $coupon): JsonResponse
    {
        try {
            $this->coupons->delete($coupon);

            return ApiResponse::success(null, 'Coupon deleted successfully');
        } catch (Throwable $throwable) {
            Log::error('Failed to delete coupon', [
                'uuid' => $coupon->uuid,
                'error' => $throwable->getMessage(),
            ]);

            return ApiResponse::error('Failed to delete the coupon', null, 500);
        }
    }

    public function redemptions(Request $request, Coupon $coupon): JsonResponse
    {
        try {
            $redemptions = $this->coupons->redemptions($coupon, $this->perPage($request));

            return ApiResponse::success(
                [
                    'data' => CouponRedemptionResource::collection($redemptions->items())->resolve(),
                    'pagination' => [
                        'total' => $redemptions->total(),
                        'count' => $redemptions->count(),
                        'per_page' => $redemptions->perPage(),
                        'current_page' => $redemptions->currentPage(),
                        'last_page' => $redemptions->lastPage(),
                        'from' => $redemptions->firstItem(),
                        'to' => $redemptions->lastItem(),
                    ],
                ],
                'Coupon redemptions retrieved successfully',
            );
        } catch (Throwable $throwable) {
            Log::error('Failed to list coupon redemptions', [
                'uuid' => $coupon->uuid,
                'error' => $throwable->getMessage(),
            ]);

            return ApiResponse::error('Failed to retrieve coupon redemptions', null, 500);
        }
    }

    /** Bounded so a crafted `per_page` cannot ask for the whole table. */
    private function perPage(Request $request): int
    {
        return min(100, max(1, (int) $request->input('per_page', 15)));
    }
}
