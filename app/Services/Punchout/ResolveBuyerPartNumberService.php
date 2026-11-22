<?php

namespace App\Services\Punchout;

use App\Models\BuyerItemMapping;
use App\Models\Product;
use App\Models\PunchoutBuyer;
use App\Models\Variant;

/**
 * Resolves the code to put on a cart line for the session's buyer.
 *
 * Precedence: the buyer's own item master (active mapping for this tenant,
 * variant-specific first), then the legacy `prices.metadata.customer_part_number`
 * value carried in the pricing payload. The legacy fallback disappears once the
 * price metadata has been migrated (see the buyer part number plan).
 */
class ResolveBuyerPartNumberService
{
    public function handle(
        ?PunchoutBuyer $buyer,
        int $tenantId,
        Product $product,
        ?Variant $variant = null,
        ?string $legacy = null,
    ): ?string {
        if ($buyer !== null && $buyer->buyer_party_id !== null) {
            $code = $this->fromMapping($buyer, $tenantId, $product, $variant);

            if ($code !== null) {
                return $code;
            }
        }

        $legacy = trim((string) $legacy);

        return $legacy === '' ? null : $legacy;
    }

    /**
     * Batch lookup for catalog listings: one query for the whole page.
     *
     * Product-wide mappings only; variant-specific codes are resolved on the
     * product detail page where the variant is known.
     *
     * @param  array<int, int>  $productIds
     * @return array<int, string> Product id => buyer part number
     */
    public function forProductIds(?PunchoutBuyer $buyer, int $tenantId, array $productIds): array
    {
        if ($buyer?->buyer_party_id === null || $productIds === []) {
            return [];
        }

        $today = now()->toDateString();

        return BuyerItemMapping::query()
            ->with('item')
            ->where('tenant_id', $tenantId)
            ->where('buyer_party_id', $buyer->buyer_party_id)
            ->whereIn('product_id', $productIds)
            ->whereNull('variant_id')
            ->where('is_active', true)
            ->whereHas('item', fn ($query) => $query->where('is_active', true))
            ->where(fn ($query) => $query->whereNull('effective_from')->orWhere('effective_from', '<=', $today))
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhere('effective_to', '>=', $today))
            ->orderByDesc('is_primary')
            ->orderByDesc('id')
            ->get()
            ->groupBy('product_id')
            ->map(fn ($rows): string => trim((string) $rows->first()?->item?->code))
            ->reject(fn (string $code): bool => $code === '')
            ->all();
    }

    private function fromMapping(
        PunchoutBuyer $buyer,
        int $tenantId,
        Product $product,
        ?Variant $variant,
    ): ?string {
        $today = now()->toDateString();

        $mapping = BuyerItemMapping::query()
            ->with('item')
            ->where('tenant_id', $tenantId)
            ->where('buyer_party_id', $buyer->buyer_party_id)
            ->where('product_id', $product->id)
            ->where('is_active', true)
            ->whereHas('item', fn ($query) => $query->where('is_active', true))
            ->where(fn ($query) => $query->whereNull('effective_from')->orWhere('effective_from', '<=', $today))
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhere('effective_to', '>=', $today))
            ->where(function ($query) use ($variant): void {
                if ($variant !== null) {
                    $query->where(function ($query) use ($variant): void {
                        $query->whereNull('variant_id')->orWhere('variant_id', $variant->id);
                    });

                    return;
                }

                $query->whereNull('variant_id');
            })
            // Variant-specific rows sort ahead of product-wide rows.
            ->orderByRaw('CASE WHEN variant_id IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('is_primary')
            ->orderByDesc('id')
            ->first();

        $code = trim((string) $mapping?->item?->code);

        return $code === '' ? null : $code;
    }
}
