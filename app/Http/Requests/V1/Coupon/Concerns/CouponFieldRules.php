<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\Coupon\Concerns;

use App\Enums\CouponType;
use App\Models\Coupon;
use Closure;
use Illuminate\Validation\Rule;

/**
 * The value, date and limit rules for a coupon — everything except `code` and
 * the scope, which differ between the admin and merchant requests.
 *
 * Shared so the percentage ceiling, the "limit cannot drop below what has
 * already been used" guard and the date ordering cannot drift apart between the
 * two write paths.
 */
trait CouponFieldRules
{
    /** `10% off` needs a 100 ceiling; a fixed amount only needs a sane bound. */
    protected function isPercent(?Coupon $coupon = null): bool
    {
        $type = $this->input('type') ?? $coupon?->type;

        return (string) $type === CouponType::PERCENT;
    }

    /**
     * @param  bool  $partial  `sometimes` flavour for updates, `required` for creates.
     * @return array<string, mixed>
     */
    protected function couponFieldRules(?Coupon $coupon, bool $partial): array
    {
        $percent = $this->isPercent($coupon);

        $required = $partial ? ['sometimes', 'required'] : ['required'];
        $optional = $partial ? ['sometimes', 'nullable'] : ['nullable'];

        return [
            'label' => [...$optional, 'string', 'max:255'],
            'type' => [...$required, 'string', Rule::in(CouponType::values())],

            'value' => [...$required, 'numeric', 'gt:0', $percent ? 'max:100' : 'max:99999999'],

            'min_order_amount' => [...$optional, 'numeric', 'min:0', 'max:99999999'],
            'max_discount_amount' => [...$optional, 'numeric', 'min:0', 'max:99999999'],

            'starts_at' => [...$optional, 'date'],
            'ends_at' => [...$optional, 'date', 'after_or_equal:starts_at'],

            'usage_limit' => [
                ...$optional, 'integer', 'min:1', 'max:1000000',
                // Lowering the limit below what has already been claimed would
                // make the counter and the redemptions table disagree.
                function (string $attribute, mixed $value, Closure $fail) use ($coupon): void {
                    if ($value !== null
                        && $coupon instanceof Coupon
                        && (int) $value < (int) $coupon->used_count) {
                        $fail('The usage limit cannot be lower than the number of times this code has already been used.');
                    }
                },
            ],
            'per_user_limit' => [...$optional, 'integer', 'min:1', 'max:1000000'],

            'is_active' => [$partial ? 'sometimes' : 'nullable', 'boolean'],
        ];
    }
}
