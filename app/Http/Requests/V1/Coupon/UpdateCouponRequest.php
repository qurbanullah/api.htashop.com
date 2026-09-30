<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\Coupon;

use App\Http\Requests\V1\Coupon\Concerns\ChecksCouponCodeUniqueness;
use App\Http\Requests\V1\Coupon\Concerns\CouponFieldRules;
use App\Http\Requests\V1\Coupon\Concerns\ResolvesCouponScope;
use App\Models\Coupon;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCouponRequest extends FormRequest
{
    use ChecksCouponCodeUniqueness, CouponFieldRules, ResolvesCouponScope;

    public function authorize(): bool
    {
        return true;
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
            ...$this->scopeRules(),
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
     * @return array{0: ?int, 1: ?int}
     */
    protected function uniquenessScope(Coupon $coupon): array
    {
        return [
            $this->has('tenant_id') ? $this->scopeTenantId() : $coupon->tenant_id,
            $this->has('organization_id') ? $this->scopeOrganizationId() : $coupon->organization_id,
        ];
    }
}
