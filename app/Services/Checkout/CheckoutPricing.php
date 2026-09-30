<?php

namespace App\Services\Checkout;

use App\Models\Cart;
use App\Models\User;
use App\Services\Coupon\CouponService;
use App\Support\Checkout\CheckoutTotals;

/**
 * Everything that turns a basket into a total: item prices, delivery, tax and
 * any coupon.
 *
 * Delivery and tax are decided here from `config/shipping.php`; the client
 * never sends them. A request that includes `shipping_fee` or `discount` is
 * ignored, which is what stops a crafted checkout from discounting itself.
 */
class CheckoutPricing
{
    public function __construct(
        protected CartPricing $cartPricing,
        protected CouponService $coupons,
    ) {}

    /**
     * @param  string|null  $couponCode  Codes the customer entered; an invalid
     *                                   one throws `CouponException`, so the caller decides whether to fail the
     *                                   request (checkout) or report it (quote).
     * @param  int|null  $tenantId  Scope the coupon is resolved in; a code that
     *                              only exists for another tenant resolves as "not found".
     */
    public function forCart(
        Cart $cart,
        ?string $couponCode = null,
        ?User $user = null,
        ?int $tenantId = null,
        ?int $organizationId = null,
    ): CheckoutTotals {
        $subtotal = $this->cartPricing->subtotal($cart);
        $currency = $this->currencyFor($cart);

        $shipping = $this->shippingFor($subtotal, $currency);

        $coupon = filled($couponCode)
            ? $this->coupons->resolve((string) $couponCode, $subtotal, $user, $cart->session_id, $tenantId, $organizationId)
            : null;

        $discount = $coupon ? $this->coupons->discountFor($coupon, $subtotal) : 0.0;

        // Tax is charged after the discount: the customer should not pay tax on
        // money they never handed over.
        $taxable = max(0.0, $subtotal - $discount);
        $tax = $this->taxFor($taxable);

        return new CheckoutTotals(
            subtotal: $subtotal,
            shippingFee: $shipping['fee'],
            tax: $tax,
            discount: $discount,
            total: round(max(0.0, $taxable + $shipping['fee'] + $tax), 2),
            currency: $currency,
            freeShipping: $shipping['fee'] <= 0,
            freeShippingThreshold: $shipping['threshold'],
            couponCode: $coupon?->code,
            couponLabel: $coupon?->label,
        );
    }

    /**
     * @return array{fee: float, threshold: ?float}
     */
    private function shippingFor(float $subtotal, string $currency): array
    {
        if (! config('shipping.enabled', true) || $subtotal <= 0) {
            return ['fee' => 0.0, 'threshold' => null];
        }

        $rate = config("shipping.rates.{$currency}") ?? config('shipping.default', []);

        $threshold = $this->nullableFloat(data_get($rate, 'free_over'));

        if ($threshold !== null && $subtotal >= $threshold) {
            return ['fee' => 0.0, 'threshold' => $threshold];
        }

        return [
            'fee' => round(max(0.0, (float) data_get($rate, 'flat_rate', 0)), 2),
            'threshold' => $threshold,
        ];
    }

    private function taxFor(float $taxable): float
    {
        if (! config('shipping.tax.enabled', false) || $taxable <= 0) {
            return 0.0;
        }

        $rate = max(0.0, (float) config('shipping.tax.rate', 0));

        return round($taxable * ($rate / 100), 2);
    }

    private function currencyFor(Cart $cart): string
    {
        return strtoupper((string) ($cart->currency ?: config('payment.currency', 'PKR')));
    }

    /** Config values arrive as strings from env; `0` and `''` mean "no threshold". */
    private function nullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '' || (float) $value <= 0) {
            return null;
        }

        return (float) $value;
    }
}
