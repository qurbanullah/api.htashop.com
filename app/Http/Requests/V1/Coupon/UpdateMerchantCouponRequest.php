<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\Coupon;

use App\Http\Requests\V1\Coupon\Concerns\ChecksCouponCodeUniqueness;
use App\Http\Requests\V1\Coupon\Concerns\CouponFieldRules;
use App\Http\Requests\V1\Coupon\Concerns\ResolvesMerchantCouponScope;
use App\Models\Coupon;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMerchantCouponRequest extends FormRequest
{
    use ChecksCouponCodeUniqueness, CouponFieldRules, ResolvesMerchantCouponScope;

    public function authorize(): bool
    {
        return $this->merchantScope() !== null;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('code')) {
            $this->merge(['code' => Coupon::normaliseCode($this->input('code'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $coupon = $this->route('coupon');

        return [
            'code' => ['sometimes', 'required', 'string', 'max:50'],
            ...$this->couponFieldRules($coupon instanceof Coupon ? $coupon : null, partial: true),
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return $this->couponCodeUniquenessAfter();
    }

    /**
     * A merchant may edit a code but never move it: uniqueness is judged in the
     * merchant's own scope, whatever the coupon was before.
     *
     * @return array{0: ?int, 1: ?int}
     */
    protected function uniquenessScope(Coupon $coupon): array
    {
        $scope = $this->merchantScope();

        return [$scope?->tenantId, $scope?->organizationId];
    }
}
