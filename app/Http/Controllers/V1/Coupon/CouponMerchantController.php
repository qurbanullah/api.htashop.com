<?php

declare(strict_types=1);

namespace App\Http\Controllers\V1\Coupon;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Coupon\StoreMerchantCouponRequest;
use App\Http\Requests\V1\Coupon\UpdateMerchantCouponRequest;
use App\Http\Resources\V1\Coupon\CouponCollection;
use App\Http\Resources\V1\Coupon\CouponRedemptionResource;
use App\Http\Resources\V1\Coupon\CouponResource;
use App\Http\Responses\V1\ApiResponse;
use App\Models\Coupon;
use App\Models\User;
use App\Services\Coupon\CouponMerchantService;
use App\Services\Coupon\CouponScope;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Merchant CRUD for discount codes, scoped to the caller's membership.
 *
 * The scope is never taken from the request: it comes from the authenticated
 * user's active membership (organization outranks tenant), and every coupon
 * outside it is reported as missing. Route model binding resolves `{coupon}` by
 * uuid before the ownership check in the service.
 */
class CouponMerchantController extends Controller
{
    public function __construct(
        private CouponMerchantService $coupons,
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $coupons = $this->coupons->paginate([
                'search' => $request->input('search'),
                'type' => $request->input('type'),
                'is_active' => $request->input('is_active'),
                'sort' => $request->input('sort'),
                'order' => $request->input('order'),
            ], $this->scope($request), $this->perPage($request));

            return ApiResponse::success(
                new CouponCollection($coupons),
                'Coupons retrieved successfully',
            );
        } catch (Throwable $throwable) {
            return $this->handle($throwable, 'Failed to retrieve coupons', [
                'error' => $throwable->getMessage(),
            ]);
        }
    }

    public function statistics(Request $request): JsonResponse
    {
        try {
            return ApiResponse::success(
                $this->coupons->statistics($this->scope($request)),
                'Coupon statistics retrieved successfully',
            );
        } catch (Throwable $throwable) {
            return $this->handle($throwable, 'Failed to retrieve coupon statistics', [
                'error' => $throwable->getMessage(),
            ]);
        }
    }

    public function show(Request $request, Coupon $coupon): JsonResponse
    {
        try {
            return ApiResponse::success(
                new CouponResource($this->coupons->find($coupon, $this->scope($request))),
                'Coupon retrieved successfully',
            );
        } catch (Throwable $throwable) {
            return $this->handle($throwable, 'Failed to retrieve the coupon', [
                'uuid' => $coupon->uuid,
                'error' => $throwable->getMessage(),
            ]);
        }
    }

    public function store(StoreMerchantCouponRequest $request): JsonResponse
    {
        try {
            return ApiResponse::success(
                new CouponResource($this->coupons->create($this->scope($request), $request->validated())),
                'Coupon created successfully',
                201,
            );
        } catch (Throwable $throwable) {
            return $this->handle($throwable, 'Failed to create the coupon', [
                'error' => $throwable->getMessage(),
            ]);
        }
    }

    public function update(UpdateMerchantCouponRequest $request, Coupon $coupon): JsonResponse
    {
        try {
            return ApiResponse::success(
                new CouponResource($this->coupons->update($coupon, $this->scope($request), $request->validated())),
                'Coupon updated successfully',
            );
        } catch (Throwable $throwable) {
            return $this->handle($throwable, 'Failed to update the coupon', [
                'uuid' => $coupon->uuid,
                'error' => $throwable->getMessage(),
            ]);
        }
    }

    public function destroy(Request $request, Coupon $coupon): JsonResponse
    {
        try {
            $this->coupons->delete($coupon, $this->scope($request));

            return ApiResponse::success(null, 'Coupon deleted successfully');
        } catch (Throwable $throwable) {
            return $this->handle($throwable, 'Failed to delete the coupon', [
                'uuid' => $coupon->uuid,
                'error' => $throwable->getMessage(),
            ]);
        }
    }

    public function redemptions(Request $request, Coupon $coupon): JsonResponse
    {
        try {
            $redemptions = $this->coupons->redemptions($coupon, $this->scope($request), $this->perPage($request));

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
            return $this->handle($throwable, 'Failed to retrieve coupon redemptions', [
                'uuid' => $coupon->uuid,
                'error' => $throwable->getMessage(),
            ]);
        }
    }

    /**
     * The caller's coupon scope. A user with no active membership has no scope
     * to manage codes in, which is a 403 rather than an empty list.
     */
    private function scope(Request $request): CouponScope
    {
        $user = $request->user('api');

        $scope = $user instanceof User ? CouponScope::forUser($user) : null;

        if ($scope === null) {
            throw new AccessDeniedHttpException('No active organization or tenant is associated with your account.');
        }

        return $scope;
    }

    /**
     * Turn an unexpected failure into a 500 without swallowing the exceptions
     * that already carry a status: a coupon outside the caller's scope must stay
     * a 404, and a missing membership a 403.
     *
     * @param  array<string, mixed>  $context
     */
    private function handle(Throwable $throwable, string $message, array $context): JsonResponse
    {
        if ($throwable instanceof HttpExceptionInterface || $throwable instanceof ModelNotFoundException) {
            throw $throwable;
        }

        Log::error($message, $context);

        return ApiResponse::error($message, null, 500);
    }

    /** Bounded so a crafted `per_page` cannot ask for the whole table. */
    private function perPage(Request $request): int
    {
        return min(100, max(1, (int) $request->input('per_page', 15)));
    }
}
