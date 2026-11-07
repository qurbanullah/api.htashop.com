<?php

namespace App\Services\Shipping;

use App\Models\Cart;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Variant;
use App\Models\Warehouse;

/**
 * Picks the warehouse a basket would ship from.
 *
 * Phase 5 resolves a single origin: the warehouse with the most available stock
 * for the basket's lines. Orders that genuinely need to split across origins
 * are a later, separate concern — but rates and carrier pickup already use the
 * result, so scoping a rate to an origin is meaningful today.
 */
class ShippingOriginResolver
{
    public function forCart(Cart $cart): ?Warehouse
    {
        $items = $cart->items()->get(['product_id', 'variant_id']);

        if ($items->isEmpty()) {
            return null;
        }

        $productIds = $items->pluck('product_id')->filter()->unique()->values()->all();
        $variantIds = $items->pluck('variant_id')->filter()->unique()->values()->all();

        if ($productIds === [] && $variantIds === []) {
            return null;
        }

        $row = Inventory::query()
            ->where('is_active', true)
            ->whereNotNull('warehouse_id')
            ->where(function ($query) use ($productIds, $variantIds) {
                $query->where(function ($products) use ($productIds) {
                    $products->whereIn('stockable_type', [Product::class, 'product'])
                        ->whereIn('stockable_id', $productIds);
                });

                if ($variantIds !== []) {
                    $query->orWhere(function ($variants) use ($variantIds) {
                        $variants->whereIn('stockable_type', [Variant::class, 'variant'])
                            ->whereIn('stockable_id', $variantIds);
                    });
                }
            })
            ->selectRaw('warehouse_id, SUM(quantity - reserved) as available_units')
            ->groupBy('warehouse_id')
            ->orderByDesc('available_units')
            ->first();

        if ($row === null || (float) $row->available_units <= 0) {
            return null;
        }

        return Warehouse::query()
            ->whereKey($row->warehouse_id)
            ->where('is_active', true)
            ->first();
    }
}
