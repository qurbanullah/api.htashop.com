<?php

namespace App\Services\Pricing;

use App\Models\Price;
use App\Models\Product;
use App\Models\Variant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class PriceResolver
{
    /**
     * Resolve the effective price for a product/variant in a given context.
     *
     * Priority: Contract → Catalog → Standard
     * Within each tier, higher `priority` value wins.
     */
    public function resolve(
        Model $priceable,
        ?int $contractId = null,
        ?array $catalogIds = null,
        float $quantity = 1,
    ): ?Price {
        $prices = $this->getApplicablePrices($priceable, $contractId, $catalogIds, $quantity);

        return $prices->first();
    }

    /**
     * Get all applicable prices sorted by priority.
     */
    public function getApplicablePrices(
        Model $priceable,
        ?int $contractId = null,
        ?array $catalogIds = null,
        float $quantity = 1,
    ): Collection {
        return Price::query()
            ->where('priceable_type', $priceable->getMorphClass())
            ->where('priceable_id', $priceable->getKey())
            ->where('is_active', true)
            ->where(function ($query) use ($contractId, $catalogIds) {
                // Contract-specific (highest priority)
                if ($contractId) {
                    $query->orWhere('contract_id', $contractId);
                }
                // Catalog-specific
                if ($catalogIds) {
                    $query->orWhereIn('catalog_id', (array) $catalogIds);
                }
                // Standard (fallback)
                $query->orWhere(function ($q) {
                    $q->whereNull('contract_id')->whereNull('catalog_id');
                });
            })
            // Quantity range filter
            ->where(function ($query) use ($quantity) {
                $query->whereNull('min_quantity')
                    ->orWhere('min_quantity', '<=', $quantity);
            })
            ->where(function ($query) use ($quantity) {
                $query->whereNull('max_quantity')
                    ->orWhere('max_quantity', '>=', $quantity);
            })
            // Time-bound validity
            ->where(function ($query) {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            })
            // Sort: contract first, then catalog, then standard; highest priority within each
            ->orderByRaw('CASE WHEN contract_id IS NOT NULL THEN 1 WHEN catalog_id IS NOT NULL THEN 2 ELSE 3 END')
            ->orderByDesc('priority')
            ->get();
    }

    /**
     * Get all price tiers for a product/variant.
     */
    public function getAllPrices(Model $priceable): Collection
    {
        return Price::query()
            ->where('priceable_type', $priceable->getMorphClass())
            ->where('priceable_id', $priceable->getKey())
            ->with(['contract', 'catalog', 'currency', 'unit'])
            ->orderByRaw('CASE WHEN contract_id IS NOT NULL THEN 1 WHEN catalog_id IS NOT NULL THEN 2 ELSE 3 END')
            ->orderByDesc('priority')
            ->orderBy('min_quantity')
            ->get();
    }

    /**
     * Get the effective base price only (ignoring sale_price).
     */
    public function resolveBasePrice(Model $priceable, ?int $contractId = null, ?array $catalogIds = null, float $quantity = 1): ?string
    {
        $price = $this->resolve($priceable, $contractId, $catalogIds, $quantity);

        return $price?->base_price;
    }
}
