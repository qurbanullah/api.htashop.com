<?php

namespace App\Services\Checkout;

use App\Models\Cart;
use App\Models\Product;
use App\Models\Variant;
use Illuminate\Validation\ValidationException;

/**
 * What the items in a cart cost.
 *
 * Extracted from `CheckoutService` so the quote endpoint and order placement
 * price a basket identically — a quote that disagrees with the order is worse
 * than showing no quote at all.
 *
 * Prices come from the variant/product `metadata` price, which is what the cart
 * already shows. (`App\Services\Pricing\PriceResolver` resolves the `prices`
 * table instead; reconciling the two is a pricing decision, not a checkout one.)
 */
class CartPricing
{
    /**
     * @return array{unit_price: float, base_price: ?float, currency: string}
     */
    public function linePrice(?Product $product, ?Variant $variant): array
    {
        $metadata = $variant?->metadata ?? $product?->metadata ?? [];

        $base = (float) data_get($metadata, 'price', 0);
        $sale = (float) data_get($metadata, 'sale_price', 0);
        $currency = data_get($metadata, 'currency', 'USD') ?: 'USD';

        $effective = ($sale > 0 && $sale < $base) ? $sale : $base;

        return [
            'unit_price' => $effective,
            'base_price' => $base > 0 ? $base : null,
            'currency' => $currency,
        ];
    }

    /**
     * Cart subtotal, rounded per line exactly as the order will be.
     */
    public function subtotal(Cart $cart): float
    {
        $subtotal = 0.0;

        foreach ($cart->items()->with(['product', 'variant'])->get() as $item) {
            $unit = $this->linePrice($item->product, $item->variant)['unit_price'];
            $subtotal += round($unit * (float) $item->quantity, 2);
        }

        return round($subtotal, 2);
    }

    /**
     * Refuse a basket that cannot be delivered: a withdrawn product, or more
     * units than we hold.
     *
     * @throws ValidationException
     */
    public function assertPurchasable(Cart $cart): void
    {
        foreach ($cart->items()->with(['product', 'variant'])->get() as $item) {
            $this->assertItemStock($item->product, $item->variant, (int) $item->quantity);
        }
    }

    private function assertItemStock(?Product $product, ?Variant $variant, int $quantity): void
    {
        if (! $product || ! $product->is_active || $product->status !== 'active') {
            $name = $product?->name ?? 'Product';
            throw ValidationException::withMessages(['cart' => "The product \"{$name}\" is not available."]);
        }

        $stockable = $variant ?? $product;
        $inventories = $stockable->inventories()->where('track_inventory', true)->get();

        if ($inventories->isEmpty()) {
            return;
        }

        $available = $inventories->sum(fn ($inventory) => max(0, (float) $inventory->available));

        if ($available < $quantity) {
            throw ValidationException::withMessages([
                'cart' => 'Insufficient stock for "'.($variant?->name ?? $product->name).'".',
            ]);
        }
    }
}
