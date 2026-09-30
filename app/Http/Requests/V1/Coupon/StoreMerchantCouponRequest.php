<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\Coupon;

use App\Http\Requests\V1\Coupon\Concerns\CouponFieldRules;
use App\Http\Requests\V1\Coupon\Concerns\ResolvesMerchantCouponScope;
use App\Models\Coupon;
use App\Rules\UniqueCouponCode;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A merchant creating a discount code for their own scope.
 *
 * There are no `tenant_id` / `organization_id` rules on purpose: the scope is
 * derived from the caller's membership, so those fields are neither accepted
 * nor validated. `validated()` therefore never carries them.
 */
class StoreMerchantCouponRequest extends FormRequest
{
    use CouponFieldRules, ResolvesMerchantCouponScope;

    /** No membership means no scope to manage codes in. */
    public function authorize(): bool
    {
        return $this->merchantScope() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => Coupon::normaliseCode($this->input('code')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $scope = $this->merchantScope();

        return [
            'code' => [
                'required', 'string', 'max:50',
                new UniqueCouponCode($scope?->tenantId, $scope?->organizationId),
            ],
            ...$this->couponFieldRules(null, partial: false),
        ];
    }
}
