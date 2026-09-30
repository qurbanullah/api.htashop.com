<?php

namespace App\Services\Coupon;

use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Applying, recording and un-recording discount codes.
 *
 * All the rules that decide whether a coupon is usable live here, so the quote
 * endpoint and order placement cannot disagree about the answer.
 */
class CouponService
{
    /**
     * Look up a code and confirm the customer may use it *now*.
     *
     * A code is only visible within its own scope (organization → tenant →
     * global), so one merchant's `EID10` can neither be spent on another
     * merchant's store nor revealed to exist. A code found only in a scope the
     * shopper is not in is reported as `NOT_FOUND` on purpose — distinguishing
     * "does not exist" from "belongs to someone else" would leak the codes
     * other merchants are running.
     *
     * @throws CouponException with a translatable reason token.
     */
    public function resolve(
        string $code,
        float $subtotal,
        ?User $user = null,
        ?string $sessionId = null,
        ?int $tenantId = null,
        ?int $organizationId = null,
    ): Coupon {
        $coupon = Coupon::query()
            ->where('code', Coupon::normaliseCode($code))
            ->withinScope($tenantId, $organizationId)
            ->orderBySpecificity()
            ->first();

        if (! $coupon) {
            throw new CouponException(CouponException::NOT_FOUND);
        }

        if (! $coupon->is_active) {
            throw new CouponException(CouponException::INACTIVE);
        }

        $now = now();

        if ($coupon->starts_at && $coupon->starts_at->isAfter($now)) {
            throw new CouponException(CouponException::NOT_STARTED);
        }

        if ($coupon->ends_at && $coupon->ends_at->isBefore($now)) {
            throw new CouponException(CouponException::EXPIRED);
        }

        if ($coupon->min_order_amount !== null && $subtotal < (float) $coupon->min_order_amount) {
            throw new CouponException(CouponException::MIN_ORDER);
        }

        if ($coupon->hasReachedUsageLimit()) {
            throw new CouponException(CouponException::USAGE_LIMIT);
        }

        if ($coupon->per_user_limit !== null
            && $this->redemptionCountFor($coupon, $user, $sessionId) >= $coupon->per_user_limit) {
            throw new CouponException(CouponException::ALREADY_USED);
        }

        return $coupon;
    }

    /**
     * The amount a coupon takes off, in the order currency.
     *
     * Never more than the subtotal (a discount cannot create a negative order)
     * and never negative.
     */
    public function discountFor(Coupon $coupon, float $subtotal): float
    {
        if ($subtotal <= 0) {
            return 0.0;
        }

        $value = (float) $coupon->value;

        $discount = $coupon->isPercent()
            ? $subtotal * ($value / 100)
            : $value;

        if ($coupon->isPercent() && $coupon->max_discount_amount !== null) {
            $discount = min($discount, (float) $coupon->max_discount_amount);
        }

        return round(max(0.0, min($discount, $subtotal)), 2);
    }

    /**
     * Record the use and bump the counter.
     *
     * Must be called inside the order's transaction. The row is re-read under a
     * lock and the limit re-checked, so two customers racing for the last use of
     * a limited coupon cannot both win — which a plain `increment()` would
     * happily allow with several API replicas.
     */
    public function redeem(
        Coupon $coupon,
        Order $order,
        float $amount,
        ?User $user = null,
        ?string $sessionId = null,
    ): CouponRedemption {
        $locked = Coupon::query()->whereKey($coupon->getKey())->lockForUpdate()->firstOrFail();

        if ($locked->hasReachedUsageLimit()) {
            throw new CouponException(CouponException::USAGE_LIMIT);
        }

        $locked->increment('used_count');

        return CouponRedemption::create([
            'coupon_id' => $locked->id,
            'order_id' => $order->id,
            'user_id' => $user?->id,
            'session_id' => $user ? null : $sessionId,
            'code' => $locked->code,
            'amount' => $amount,
            'currency' => $order->currency,
        ]);
    }

    /**
     * Give the use back when an order does not go ahead.
     *
     * Without this, cancelling a discounted order would permanently consume one
     * of a limited coupon's uses.
     */
    public function releaseFor(Order $order): void
    {
        $redemptions = CouponRedemption::query()
            ->where('order_id', $order->id)
            ->get();

        if ($redemptions->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($redemptions): void {
            foreach ($redemptions as $redemption) {
                $coupon = Coupon::query()
                    ->whereKey($redemption->coupon_id)
                    ->lockForUpdate()
                    ->first();

                if ($coupon) {
                    $coupon->update(['used_count' => max(0, $coupon->used_count - 1)]);
                }

                $redemption->delete();
            }
        });
    }

    /** How many times this customer (or this browser) has used the coupon. */
    private function redemptionCountFor(Coupon $coupon, ?User $user, ?string $sessionId): int
    {
        $query = $coupon->redemptions();

        if ($user) {
            return $query->where('user_id', $user->id)->count();
        }

        if (blank($sessionId)) {
            // Nothing identifies the customer, so a per-customer cap cannot be
            // enforced; the global usage limit still applies.
            return 0;
        }

        return $query->where('session_id', $sessionId)->count();
    }
}
