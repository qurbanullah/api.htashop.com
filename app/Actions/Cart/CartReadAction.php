<?php

namespace App\Actions\Cart;

use App\Models\Cart;

class CartReadAction
{
    public function handle(Cart $cart): array
    {
        $items = $cart->items()->with(['product', 'variant'])->get();

        $lines = $items->map(fn ($item) => [
            'id' => $item->id,
            'uuid' => $item->uuid,
            'product_id' => $item->product_id,
            'variant_id' => $item->variant_id,
            'quantity' => $item->quantity,
            'unit_price' => (float) $item->unit_price,
            'base_price' => $item->base_price !== null ? (float) $item->base_price : null,
            'currency' => $item->currency,
            'name' => data_get($item->metadata, 'name') ?? $item->product?->name,
            'slug' => data_get($item->metadata, 'slug') ?? $item->product?->slug,
            'sku' => data_get($item->metadata, 'sku'),
            'variant_name' => data_get($item->metadata, 'variant_name'),
            'image_url' => data_get($item->metadata, 'image_url'),
        ])->all();

        $subtotal = $items->sum(fn ($item) => (float) $item->unit_price * $item->quantity);
        $count = $items->sum('quantity');

        return [
            'uuid' => $cart->uuid,
            'session_id' => $cart->session_id,
            'currency' => $cart->currency,
            'status' => $cart->status,
            'items' => $lines,
            'count' => (int) $count,
            'subtotal' => (float) $subtotal,
        ];
    }
}
