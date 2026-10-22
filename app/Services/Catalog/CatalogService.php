<?php

namespace App\Services\Catalog;

use App\Actions\Catalog\CatalogPopularityReadAction;
use App\Actions\Catalog\CatalogProductShowAction;
use App\Actions\Catalog\CatalogProductsReadAction;
use App\Enums\SalesChannel;
use App\Helpers\CacheHelper;
use App\Models\Brand;
use App\Models\Feature;
use App\Models\Product;
use Illuminate\Pagination\LengthAwarePaginator;

class CatalogService
{
    private const POPULARITY_CACHE_TTL = 900; // 15 minutes

    public function __construct(
        protected CatalogProductsReadAction $productsReadAction,
        protected CatalogProductShowAction $productShowAction,
        protected CatalogPopularityReadAction $popularityReadAction,
    ) {}

    public function products(array $filters = []): LengthAwarePaginator
    {
        $sort = data_get($filters, 'sort', 'newest');
        $popularityRanking = [];

        if (in_array($sort, ['best_sellers', 'trending'], true)) {
            $popularityRanking = CacheHelper::remember(
                ['catalog', 'popularity'],
                "catalog:popularity:{$sort}",
                self::POPULARITY_CACHE_TTL,
                fn () => $this->popularityReadAction->handle($sort),
            );
        }

        return $this->productsReadAction->handle($filters, $popularityRanking);
    }

    public function show(string $key, array $filters = []): Product
    {
        return $this->productShowAction->handle($key, $filters);
    }

    public function filters(array $filters = []): array
    {
        $channel = data_get($filters, 'channel', SalesChannel::STOREFRONT);
        $tenantId = data_get($filters, 'tenant_id');

        $brands = Brand::query()
            ->where('is_active', true)
            ->whereHas('products', fn ($query) => $query->forChannel($channel)->forTenant($tenantId))
            ->orderBy('name')
            ->get(['id', 'name']);

        $features = Feature::query()
            ->where('is_active', true)
            ->whereHas('products', fn ($query) => $query->forChannel($channel)->forTenant($tenantId))
            ->orderBy('name')
            ->get(['id', 'name']);

        $priceRange = Product::query()
            ->where('is_active', true)
            ->where('status', 'active')
            ->forChannel($channel)
            ->forTenant($tenantId)
            ->whereNotNull('metadata')
            ->selectRaw('
                MIN(CAST(JSON_UNQUOTE(JSON_EXTRACT(metadata, "$.price")) AS DECIMAL(12,2))) as min_price,
                MAX(CAST(JSON_UNQUOTE(JSON_EXTRACT(metadata, "$.price")) AS DECIMAL(12,2))) as max_price
            ')
            ->first();

        return [
            'brands' => $brands,
            'features' => $features,
            'price_range' => [
                'min' => $priceRange?->min_price !== null ? (float) $priceRange->min_price : 0,
                'max' => $priceRange?->max_price !== null ? (float) $priceRange->max_price : 0,
            ],
        ];
    }
}
