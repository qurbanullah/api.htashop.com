<?php

namespace App\Actions\Cart;

use App\Models\Cart;
use App\Models\Product;
use App\Models\Variant;
use Illuminate\Validation\ValidationException;

class CartAddItemAction
{
    public function handle(Cart $cart, array $data): Cart
    {
        $product = Product::query()->find(data_get($data, 'product_id'));

        if (! $product || ! $product->is_active || $product->status !== 'active') {
            throw ValidationException::withMessages([
                'product_id' => 'The selected product is not available.',
            ]);
        }

        $variant = null;
        if (data_get($data, 'variant_id')) {
            $variant = Variant::query()
                ->where('id', data_get($data, 'variant_id'))
                ->where('product_id', $product->id)
                ->first();

            if (! $variant) {
                throw ValidationException::withMessages([
                    'variant_id' => 'The selected variant is not valid for this product.',
                ]);
            }
        }

        $quantity = max(1, (int) data_get($data, 'quantity', 1));

        $this->validateStock($product, $variant, $quantity);

        $pricing = $this->resolvePricing($product, $variant);

        $existing = $cart->items()
            ->where('product_id', $product->id)
            ->when($variant, fn ($query) => $query->where('variant_id', $variant->id), fn ($query) => $query->whereNull('variant_id'))
            ->first();

        if ($existing) {
            $existing->update([
                'quantity' => $existing->quantity + $quantity,
                'unit_price' => $pricing['unit_price'],
                'base_price' => $pricing['base_price'],
                'currency' => $pricing['currency'],
            ]);
        } else {
            $cart->items()->create([
                'product_id' => $product->id,
                'variant_id' => $variant?->id,
                'quantity' => $quantity,
                'unit_price' => $pricing['unit_price'],
                'base_price' => $pricing['base_price'],
                'currency' => $pricing['currency'],
                'metadata' => [
                    'name' => $product->name,
                    'slug' => $product->slug,
                    'sku' => $variant?->sku ?? $product->sku,
                    'variant_name' => $variant?->name,
                    'image_url' => $this->resolveImageUrl($product, $variant),
                ],
            ]);
        }

        if ($cart->currency !== $pricing['currency']) {
            $cart->update(['currency' => $pricing['currency']]);
        }

        return $cart->load('items');
    }

    private function resolvePricing(Product $product, ?Variant $variant): array
    {
        $metadata = $variant?->metadata ?? $product->metadata ?? [];

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

    private function validateStock(Product $product, ?Variant $variant, int $quantity): void
    {
        $stockable = $variant ?? $product;
        $inventories = $stockable->inventories()->where('track_inventory', true)->get();

        if ($inventories->isEmpty()) {
            return;
        }

        $available = $inventories->sum(fn ($inventory) => max(0, (float) $inventory->available));

        if ($available < $quantity) {
            throw ValidationException::withMessages([
                'quantity' => 'Requested quantity exceeds available stock.',
            ]);
        }
    }

    private function resolveImageUrl(Product $product, ?Variant $variant): ?string
    {
        $stockable = $variant ?? $product;
        $dam = $stockable->dams()->where('collection_name', 'featured')->first();

        if (! $dam || ! $dam->object_key) {
            return null;
        }

        return 'https://cdn.htashop.com/' . ltrim($dam->object_key, '/');
    }
}
