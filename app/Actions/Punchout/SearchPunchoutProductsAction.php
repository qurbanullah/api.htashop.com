<?php

namespace App\Actions\Punchout;

use App\Actions\Catalog\CatalogProductsReadAction;
use App\Models\Product;
use App\Models\PunchoutBuyer;
use App\Services\Search\ProductSearchService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Punchout catalog listing.
 *
 * A typed query goes to Typesense: it ranks by relevance, tolerates typos and
 * matches part numbers, which a `LIKE '%term%'` scan cannot. The buyer's own
 * part numbers are resolved from the pricing tables and ranked ahead of the
 * relevance hits, because that is what a procurement user types.
 *
 * Browsing (no query) and every Typesense failure fall back to the database
 * action, so a search outage never ends a live buyer session.
 *
 * The index is only a lookup: products are re-fetched through the same tenant +
 * channel scopes as the database path, so a stale document can never surface
 * another tenant's product or an item that left the punchout channel.
 */
class SearchPunchoutProductsAction
{
    /** One weight per field, in the same order. */
    private const QUERY_BY = 'name,sku,part_number,model_number,category_names,brand_names,tags,summary,description';

    private const QUERY_BY_WEIGHTS = '4,5,6,4,1,1,1,2,1';

    /** Typesense refuses per_page above 250. */
    private const MAX_WINDOW = 250;

    public function __construct(
        protected ProductSearchService $productSearchService,
        protected CatalogProductsReadAction $productsReadAction,
        protected MatchPunchoutCustomerPartNumbersAction $matchCustomerPartNumbers,
    ) {}

    /**
     * @param  array<string, mixed>  $filters  Validated request filters plus the
     *                                         server-side `tenant_id` and `channel`.
     */
    public function handle(array $filters, ?PunchoutBuyer $buyer = null): LengthAwarePaginator
    {
        $query = trim((string) data_get($filters, 'search', ''));
        $page = max(1, (int) data_get($filters, 'page', 1));
        $perPage = min(48, max(1, (int) data_get($filters, 'per_page', 12)));

        $tenantId = (int) data_get($filters, 'tenant_id');
        $partNumberIds = $query === ''
            ? []
            : $this->matchCustomerPartNumbers->handle($buyer, $tenantId, $query);

        if ($query === '' || ! $this->productSearchService->isEnabled()) {
            return $this->productsReadAction->handle(
                $partNumberIds === []
                    ? $filters
                    : array_merge($filters, ['matching_product_ids' => $partNumberIds])
            );
        }

        // Fetch from the first page so the part-number matches can be ranked
        // ahead of the relevance hits without shifting the page boundaries.
        $window = min(self::MAX_WINDOW, ($page * $perPage) + count($partNumberIds));

        try {
            $result = $this->productSearchService->search([
                'query' => $query,
                'filters' => $filters,
                'sort' => (string) data_get($filters, 'sort', 'newest'),
                'page' => 1,
                'per_page' => $window,
                'query_by' => self::QUERY_BY,
                'query_by_weights' => self::QUERY_BY_WEIGHTS,
            ]);
        } catch (Throwable $e) {
            Log::warning('Punchout product search fell back to the database: '.$e->getMessage(), [
                'tenant_id' => $tenantId,
            ]);

            return $this->productsReadAction->handle(
                $partNumberIds === []
                    ? $filters
                    : array_merge($filters, ['matching_product_ids' => $partNumberIds])
            );
        }

        $hitIds = array_values(array_filter(array_map(
            static fn (array $hit): int => (int) ($hit['id'] ?? 0),
            (array) ($result['hits'] ?? []),
        )));

        // Buyer part numbers first: the procurement user typed their own code.
        $orderedIds = array_values(array_unique(array_merge($partNumberIds, $hitIds)));
        $pageIds = array_slice($orderedIds, ($page - 1) * $perPage, $perPage);

        $total = (int) ($result['found'] ?? 0) + count(array_diff($partNumberIds, $hitIds));

        if ($pageIds === []) {
            return new Paginator([], $total, $perPage, $page);
        }

        $products = Product::query()
            ->whereIn('id', $pageIds)
            ->where('is_active', true)
            ->where('status', 'active')
            ->forTenant(data_get($filters, 'tenant_id'))
            ->forChannel(data_get($filters, 'channel'))
            ->with([
                'categories',
                'brands',
                'features',
                'salesChannels',
                'dams' => fn ($damQuery) => $damQuery
                    ->where('collection_name', 'featured')
                    ->orderByRaw('CASE WHEN sort_order IS NULL THEN 1 ELSE 0 END')
                    ->orderBy('sort_order'),
            ])
            ->get()
            ->keyBy('id');

        // Ranking comes from the merged id list, not from the database.
        $ordered = collect($pageIds)
            ->map(static fn (int $id) => $products->get($id))
            ->filter()
            ->values();

        return new Paginator($ordered, $total, $perPage, $page);
    }
}
