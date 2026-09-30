<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\Coupon\Concerns;

use App\Models\User;
use App\Services\Coupon\CouponScope;

/**
 * The scope a merchant's coupon request acts in, taken from their active
 * membership. Never read from the request body: a merchant cannot widen their
 * own reach by sending `tenant_id`.
 *
 * The result is memoised per request so `authorize()` and `rules()` agree
 * without hitting the database twice.
 */
trait ResolvesMerchantCouponScope
{
    private ?CouponScope $merchantCouponScope = null;

    private bool $merchantCouponScopeResolved = false;

    protected function merchantScope(): ?CouponScope
    {
        if (! $this->merchantCouponScopeResolved) {
            $user = $this->user('api');
            $this->merchantCouponScope = $user instanceof User ? CouponScope::forUser($user) : null;
            $this->merchantCouponScopeResolved = true;
        }

        return $this->merchantCouponScope;
    }
}
