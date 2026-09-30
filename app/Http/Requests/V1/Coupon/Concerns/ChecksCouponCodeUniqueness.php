<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\Coupon\Concerns;

use App\Models\Coupon;
use App\Rules\UniqueCouponCode;
use Illuminate\Contracts\Validation\Validator;

/**
 * Scope-aware code uniqueness for updates.
 *
 * A create can attach `UniqueCouponCode` straight to the `code` rule, because
 * the scope is in the payload. An update cannot: the scope may be changing
 * while the code is not resent, so uniqueness has to be judged against the
 * scope the coupon will have *after* the save — hence `after()`, once the field
 * rules have had their say.
 */
trait ChecksCouponCodeUniqueness
{
    /**
     * The scope to judge uniqueness in, given the coupon being updated.
     *
     * @return array{0: ?int, 1: ?int} [tenant_id, organization_id]
     */
    abstract protected function uniquenessScope(Coupon $coupon): array;

    /**
     * @return array<int, callable>
     */
    protected function couponCodeUniquenessAfter(): array
    {
        return [
            function (Validator $validator): void {
                $coupon = $this->route('coupon');

                if (! $coupon instanceof Coupon) {
                    return;
                }

                $errors = $validator->errors();

                if ($errors->has('code') || $errors->has('tenant_id') || $errors->has('organization_id')) {
                    return;
                }

                [$tenantId, $organizationId] = $this->uniquenessScope($coupon);

                $code = $this->has('code')
                    ? Coupon::normaliseCode((string) $this->input('code'))
                    : $coupon->code;

                (new UniqueCouponCode($tenantId, $organizationId, $coupon->getKey()))
                    ->validate('code', $code, function (string $message) use ($errors): void {
                        $errors->add('code', $message);
                    });
            },
        ];
    }
}
