<?php

namespace App\Services\Checkout;

use App\Models\Cart;
use App\Models\User;
use App\Services\Coupon\CouponService;
use App\Services\Pricing\DutyRateService;
use App\Services\Shipping\ShippingQuoteService;
use App\Support\Checkout\CheckoutTotals;
use App\Support\Shipping\ParcelSummary;
use App\Support\Shipping\ShippingQuote;
use Illuminate\Support\Str;

/**
 * Everything that turns a basket into a total: item prices, delivery, tax and
 * any coupon.
 *
 * Delivery and tax are decided here from the shipping configuration (zones and
 * rates, with the legacy flat rate as fallback) and the client never sends them.
 * A request that includes `shipping_fee` or `discount` is ignored, which is what
 * stops a crafted checkout from discounting itself.
 */
class CheckoutPricing
{
    public function __construct(
        protected CartPricing $cartPricing,
        protected CouponService $coupons,
        protected DutyRateService $dutyRates,
        protected ShippingQuoteService $shippingQuotes,
    ) {}

    /**
     * @param  string|null  $couponCode  Codes the customer entered; an invalid
     *                                   one throws `CouponException`, so the caller decides whether to fail the
     *                                   request (checkout) or report it (quote).
     * @param  int|null  $tenantId  Scope the coupon is resolved in; a code that
     *                              only exists for another tenant resolves as "not found".
     * @param  array<string, mixed>|null  $shippingAddress  Resolved destination snapshot
     * @param  bool  $strictShippingSelection  Fail instead of silently re-pricing
     *                                         when the chosen option vanished
     */
    public function forCart(
        Cart $cart,
        ?string $couponCode = null,
        ?User $user = null,
        ?int $tenantId = null,
        ?int $organizationId = null,
        ?array $shippingAddress = null,
        ?string $shippingRateUuid = null,
        bool $strictShippingSelection = false,
    ): CheckoutTotals {
        $subtotal = $this->cartPricing->subtotal($cart);
        $currency = $this->currencyFor($cart);

        $quote = $this->shippingFor(
            $cart,
            $subtotal,
            $currency,
            $shippingAddress,
            $shippingRateUuid,
            $tenantId,
            $strictShippingSelection,
        );
        $shippingFee = $quote->fee();
        $duty = $this->dutyForCart($cart);

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
            shippingFee: $shippingFee,
            tax: $tax,
            discount: $discount,
            total: round(max(0.0, $taxable + $shippingFee + $tax), 2),
            currency: $currency,
            freeShipping: $shippingFee <= 0,
            freeShippingThreshold: $quote->freeShippingThreshold,
            couponCode: $coupon?->code,
            couponLabel: $coupon?->label,
            dutyEstimate: $duty,
            shippingMethod: $quote->selected?->name,
            shippingRateUuid: $this->persistedRateUuid($quote),
            shippingOptions: array_map(
                fn ($option) => $option->toArray(),
                $quote->options,
            ),
            shippingSource: $quote->source,
        );
    }

    /**
     * Only real `shipping_rates` rows are persisted; the legacy fallback and
     * carrier-quoted options use synthetic ids (`config:*`, `leopards:*`).
     */
    private function persistedRateUuid(ShippingQuote $quote): ?string
    {
        $id = $quote->selected?->id;

        return $id !== null && Str::isUuid($id) ? $id : null;
    }

    /**
     * @param  array<string, mixed>|null  $shippingAddress
     */
    private function shippingFor(
        Cart $cart,
        float $subtotal,
        string $currency,
        ?array $shippingAddress,
        ?string $shippingRateUuid,
        ?int $tenantId,
        bool $strict,
    ): ShippingQuote {
        if ($subtotal <= 0) {
            return ShippingQuote::none(ParcelSummary::empty());
        }

        return $this->shippingQuotes->forCart(
            $cart,
            $shippingAddress,
            $currency,
            $subtotal,
            $shippingRateUuid,
            $tenantId,
            $strict,
        );
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

    /**
     * Estimated customs duty for the imported lines in the basket.
     *
     * Only on-demand/drop-ship/pre-order items are included: in-stock goods
     * already have their landed cost folded into the price. The figure is an
     * estimate the buyer will likely pay at customs on delivery — it is not
     * added to the order total.
     */
    private function dutyForCart(Cart $cart): float
    {
        $duty = 0.0;

        foreach ($cart->items()->with(['product', 'variant'])->get() as $item) {
            $product = $item->product;

            if (! $product || ! $product->requiresAdvancePayment()) {
                continue;
            }

            $unit = $this->cartPricing->linePrice($product, $item->variant)['unit_price'];
            $duty += $this->dutyRates->estimate(
                $unit * (float) $item->quantity,
                $product->hs_code,
                $product->origin_country,
            );
        }

        return round($duty, 2);
    }
}
