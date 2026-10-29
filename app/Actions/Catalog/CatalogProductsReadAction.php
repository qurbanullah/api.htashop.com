<?php

namespace App\Actions\Catalog;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class CatalogProductsReadAction
{
    /**
     * Portable price extraction: MariaDB returns a JSON scalar with its quotes
     * and has JSON_UNQUOTE, SQLite (used by the test suite) has neither.
     */
    private const PRICE_EXPRESSION = "CAST(REPLACE(JSON_EXTRACT(metadata, '$.price'), '\"', '') AS DECIMAL(12,2))";

    public function handle(array $filters = [], array $popularityRanking = []): LengthAwarePaginator
    {
        $minPrice = data_get($filters, 'min_price');
        $maxPrice = data_get($filters, 'max_price');
        $sort = data_get($filters, 'sort', 'newest');

        $query = Product::query()
            ->where('is_active', true)
            ->where('status', 'active')
            ->forTenant(data_get($filters, 'tenant_id'))
            ->forChannel(data_get($filters, 'channel'))
            ->with([
                'categories',
                'brands',
                'features',
                'salesChannels',
                'dams' => fn ($query) => $query
                    ->where('collection_name', 'featured')
                    ->orderByRaw('CASE WHEN sort_order IS NULL THEN 1 ELSE 0 END')
                    ->orderBy('sort_order'),
            ])
            ->when(
                data_get($filters, 'search') || ! empty(data_get($filters, 'matching_product_ids')),
                function ($query) use ($filters) {
                    $search = (string) data_get($filters, 'search', '');
                    $matchingIds = array_values(array_filter(array_map(
                        'intval',
                        (array) data_get($filters, 'matching_product_ids', [])
                    )));

                    $query->where(function ($inner) use ($search, $matchingIds) {
                        if ($search !== '') {
                            $inner->where('name', 'like', '%'.$search.'%')
                                ->orWhere('slug', 'like', '%'.$search.'%')
                                ->orWhere('summary', 'like', '%'.$search.'%')
                                ->orWhere('sku', 'like', '%'.$search.'%')
                                ->orWhere('part_number', 'like', '%'.$search.'%')
                                ->orWhere('model_number', 'like', '%'.$search.'%');
                        }

                        // Buyer part numbers resolved from the pricing tables; the
                        // index cannot hold them because they are contract-scoped.
                        if ($matchingIds !== []) {
                            $inner->orWhereIn('products.id', $matchingIds);
                        }
                    });
                }
            )
            ->when(! empty(data_get($filters, 'category_ids')), function ($query) use ($filters) {
                $query->whereHas('categories', fn ($categoryQuery) => $categoryQuery->whereIn('categories.id', data_get($filters, 'category_ids')));
            })
            ->when(! empty(data_get($filters, 'brand_ids')), function ($query) use ($filters) {
                $query->whereHas('brands', fn ($brandQuery) => $brandQuery->whereIn('brands.id', data_get($filters, 'brand_ids')));
            })
            ->when(! empty(data_get($filters, 'feature_ids')), function ($query) use ($filters) {
                $query->whereHas('features', fn ($featureQuery) => $featureQuery->whereIn('features.id', data_get($filters, 'feature_ids')));
            })
            ->when($minPrice !== null && $minPrice !== '', function ($query) use ($minPrice) {
                $query->whereRaw(self::PRICE_EXPRESSION.' >= ?', [(float) $minPrice]);
            })
            ->when($maxPrice !== null && $maxPrice !== '', function ($query) use ($maxPrice) {
                $query->whereRaw(self::PRICE_EXPRESSION.' <= ?', [(float) $maxPrice]);
            });

        $this->applySort($query, $sort, $popularityRanking);

        return $query->paginate((int) data_get($filters, 'per_page', 12));
    }

    private function applySort(Builder $query, string $sort, array $popularityRanking = []): void
    {
        match ($sort) {
            'price_asc' => $query->orderByRaw(self::PRICE_EXPRESSION.' ASC'),
            'price_desc' => $query->orderByRaw(self::PRICE_EXPRESSION.' DESC'),
            'name' => $query->orderBy('name'),
            'best_sellers', 'trending' => $this->applyPopularitySort($query, $popularityRanking),
            default => $query->orderByDesc('created_at'),
        };
    }

    private function applyPopularitySort(Builder $query, array $ranking): void
    {
        if (empty($ranking)) {
            $query->orderByDesc('created_at');

            return;
        }

        $ids = array_keys($ranking);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        // Ranked products first (best → worst), unranked products last.
        $query->orderByRaw("CASE WHEN products.id IN ($placeholders) THEN 0 ELSE 1 END ASC", $ids);
        $query->orderByRaw("FIELD(products.id, $placeholders) ASC", $ids);
    }
}
