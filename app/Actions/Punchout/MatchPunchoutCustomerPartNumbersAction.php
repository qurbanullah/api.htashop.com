<?php

namespace App\Actions\Punchout;

use App\Models\Price;
use App\Models\Product;
use App\Models\PunchoutBuyer;

/**
 * Finds products by the buyer's own part number.
 *
 * Customer part numbers live in `prices.metadata.customer_part_number`, scoped
 * to a contract or price list, so they cannot be indexed once for every buyer.
 * This query therefore runs against the database and only considers rows the
 * price resolver would accept for this buyer: their contract, their contract's
 * catalog, and unscoped (general) price rows. Another buyer's contract rows are
 * never matched.
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
        $term = mb_strtolower(trim($query));

        // Shorter terms would match almost every part number and bury the
        // relevance results from the search index.
        if (mb_strlen($term) < self::MIN_TERM_LENGTH) {
            return [];
        }

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
