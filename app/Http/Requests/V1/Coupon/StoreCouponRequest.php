<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\Coupon;

use App\Http\Requests\V1\Coupon\Concerns\CouponFieldRules;
use App\Http\Requests\V1\Coupon\Concerns\ResolvesCouponScope;
use App\Models\Coupon;
use App\Rules\UniqueCouponCode;
use Illuminate\Foundation\Http\FormRequest;

class StoreCouponRequest extends FormRequest
{
    use CouponFieldRules, ResolvesCouponScope;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Codes are stored upper-case (`Coupon::normaliseCode()`), so normalise
     * before validating — otherwise `save10` would slip past the unique rule
     * and collide with `SAVE10` at the database level.
     */
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
        return [
            'code' => [
                'required', 'string', 'max:50',
                new UniqueCouponCode($this->scopeTenantId(), $this->scopeOrganizationId()),
            ],
            ...$this->couponFieldRules(null, partial: false),
            ...$this->scopeRules(),
        ];
    }
}
