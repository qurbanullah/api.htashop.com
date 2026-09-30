<?php

declare(strict_types=1);

namespace App\Services\Coupon;

use App\Models\Coupon;
use App\Models\CouponRedemption;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Merchant-facing management of discount codes.
 *
 * Thin on purpose: it reuses `CouponAdminService` for the actual reads and
 * writes and adds the one thing admin does not need — an ownership boundary.
 * Every coupon the caller did not create is treated as absent (404, not 403),
 * so a merchant cannot use the API to discover which codes other merchants are
 * running.
 */
class CouponMerchantService
{
    public function __construct(
        private readonly CouponAdminService $coupons,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Coupon>
     */
    public function paginate(array $filters, CouponScope $scope, int $perPage): LengthAwarePaginator
    {
        return $this->coupons->paginate($filters, $perPage, $scope);
    }

    /**
     * @return array<string, int>
     */
    public function statistics(CouponScope $scope): array
    {
        return $this->coupons->statistics($scope);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(CouponScope $scope, array $data): Coupon
    {
        // Scope wins over anything that reached `$data`.
        return $this->coupons->create([...$data, ...$scope->toAttributes()]);
    }

    /**
     * A single coupon, or a 404 if it is outside the caller's scope.
     */
    public function find(Coupon $coupon, CouponScope $scope): Coupon
    {
        $this->assertOwned($coupon, $scope);

        return $coupon
            ->load(['tenant:id,name', 'organization:id,name'])
            ->loadCount('redemptions');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Coupon $coupon, CouponScope $scope, array $data): Coupon
    {
        $this->assertOwned($coupon, $scope);

        // A merchant may edit a code, never move it.
        unset($data['tenant_id'], $data['organization_id']);

        return $this->coupons->update($coupon, $data);
    }

    public function delete(Coupon $coupon, CouponScope $scope): void
    {
        $this->assertOwned($coupon, $scope);

        $this->coupons->delete($coupon);
    }

    /**
     * @return LengthAwarePaginator<int, CouponRedemption>
     */
    public function redemptions(Coupon $coupon, CouponScope $scope, int $perPage): LengthAwarePaginator
    {
        $this->assertOwned($coupon, $scope);

        return $this->coupons->redemptions($coupon, $perPage);
    }

    private function assertOwned(Coupon $coupon, CouponScope $scope): void
    {
        if (! $scope->owns($coupon)) {
            throw (new ModelNotFoundException)->setModel(Coupon::class, [$coupon->getRouteKey()]);
        }
    }
}
