<?php

namespace App\Actions\Punchout;

use App\Models\BuyerItem;
use App\Models\BuyerItemMapping;
use App\Models\Price;
use App\Models\Product;
use App\Models\PunchoutBuyer;

/**
 * Finds products by the buyer's own part number.
 *
 * The primary lookup is the buyer item master, indexed on
 * (buyer_party_id, code_key), so an exact or prefix search is a single index
 * range scan no matter how many codes the buyer publishes.
 *
 * `prices.metadata.customer_part_number` is still consulted as a transitional
 * fallback: buyers that predate the item master (or a tenant mid-migration)
 * keep working, and the JSON scan only runs when the indexed lookup finds
 * nothing. Remove it once the backfill is complete.
 */
class MatchPunchoutCustomerPartNumbersAction
{
    private const MAX_MATCHES = 100;

    private const MIN_TERM_LENGTH = 3;

    /**
     * @return array<int, int> Product ids, exact matches first.
     */
    public function handle(?PunchoutBuyer $buyer, int $tenantId, string $query): array
    {
        $term = trim($query);

        // Shorter terms would match almost every part number and bury the
        // relevance results from the search index.
        if (mb_strlen($term) < self::MIN_TERM_LENGTH) {
            return [];
        }

        if ($buyer?->buyer_party_id !== null) {
            $matched = $this->matchBuyerItems($buyer, $tenantId, BuyerItem::keyFor($term));

            if ($matched !== []) {
                return $matched;
            }
        }

        return $this->matchLegacyPriceMetadata($buyer, $tenantId, mb_strtolower($term));
    }

    /**
     * @return array<int, int>
     */
    private function matchBuyerItems(PunchoutBuyer $buyer, int $tenantId, string $key): array
    {
        return BuyerItemMapping::query()
            ->join('buyer_items', 'buyer_items.id', '=', 'buyer_item_mappings.buyer_item_id')
            ->where('buyer_item_mappings.tenant_id', $tenantId)
            ->where('buyer_item_mappings.buyer_party_id', $buyer->buyer_party_id)
            ->where('buyer_item_mappings.is_active', true)
            ->where('buyer_items.is_active', true)
            ->where('buyer_items.code_key', 'like', $key.'%')
            ->orderByRaw('CASE WHEN buyer_items.code_key = ? THEN 0 ELSE 1 END', [$key])
            ->orderByDesc('buyer_item_mappings.is_primary')
            ->orderBy('buyer_item_mappings.id')
            ->limit(self::MAX_MATCHES)
            ->pluck('buyer_item_mappings.product_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Transitional: codes only present on price rows.
     *
     * @return array<int, int>
     */
    private function matchLegacyPriceMetadata(?PunchoutBuyer $buyer, int $tenantId, string $term): array
    {
        $contractId = $buyer?->contract_id;
        $catalogId = $buyer?->contract?->catalog_id;

        // JSON_EXTRACT returns a JSON string ("ACME-1") on MariaDB and a plain
        // string on SQLite; stripping quotes makes both behave the same.
        $value = "LOWER(REPLACE(JSON_EXTRACT(metadata, ?), '\"', ''))";

        return Price::query()
            ->where('tenant_id', $tenantId)
            ->where('priceable_type', Product::class)
            ->where('is_active', true)
            ->whereNotNull('metadata')
            ->where(function ($scope) use ($contractId, $catalogId): void {
                $scope->where(function ($general): void {
                    $general->whereNull('contract_id')->whereNull('catalog_id');
                });

                if ($contractId !== null) {
                    $scope->orWhere('contract_id', $contractId);
                }

                if ($catalogId !== null) {
                    // Only unscoped rows: a contract row belonging to another
                    // buyer is never applicable, even when it names the same
                    // catalog (the resolver rejects it too).
                    $scope->orWhere(function ($catalog) use ($catalogId): void {
                        $catalog->whereNull('contract_id')->where('catalog_id', $catalogId);
                    });
                }
            })
            ->whereRaw($value.' LIKE ?', ['$.customer_part_number', '%'.$term.'%'])
            ->orderByRaw('CASE WHEN '.$value.' = ? THEN 0 ELSE 1 END', ['$.customer_part_number', $term])
            ->orderBy('priceable_id')
            ->limit(self::MAX_MATCHES)
            ->pluck('priceable_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
