<?php

namespace App\Support\Checkout;

/**
 * The money side of an order, computed server-side.
 *
 * Produced by `App\Services\Checkout\CheckoutPricing` and used by both the
 * quote endpoint and order placement, so what the customer is shown and what
 * they are charged come from one calculation.
 *
 * `freeShippingThreshold` lets the storefront say "spend PKR 2,500 more for free
 * delivery" without knowing the rule itself.
 */
final class CheckoutTotals
{
    public function __construct(
        public readonly float $subtotal,
        public readonly float $shippingFee,
        public readonly float $tax,
        public readonly float $discount,
        public readonly float $total,
        public readonly string $currency,
        public readonly bool $freeShipping,
        public readonly ?float $freeShippingThreshold = null,
        public readonly ?string $couponCode = null,
        public readonly ?string $couponLabel = null,
    ) {}

    /**
     * How much more the customer must spend to unlock free delivery, or null
     * when there is nothing to say (already free, or no threshold configured).
     */
    public function amountUntilFreeShipping(): ?float
    {
        if ($this->freeShipping || $this->freeShippingThreshold === null) {
            return null;
        }

        $remaining = round($this->freeShippingThreshold - $this->subtotal, 2);

        return $remaining > 0 ? $remaining : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'subtotal' => $this->subtotal,
            'shipping_fee' => $this->shippingFee,
            'tax' => $this->tax,
            'discount' => $this->discount,
            'total' => $this->total,
            'currency' => $this->currency,
            'free_shipping' => $this->freeShipping,
            'free_shipping_threshold' => $this->freeShippingThreshold,
            'amount_until_free_shipping' => $this->amountUntilFreeShipping(),
            'coupon_code' => $this->couponCode,
            'coupon_label' => $this->couponLabel,
        ];
    }
}
