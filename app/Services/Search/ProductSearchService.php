<?php

namespace App\Services\Search;

use App\Helpers\CacheHelper;
use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;
use Typesense\Client;
use Typesense\Exceptions\ObjectNotFound;
use Typesense\Exceptions\TypesenseClientError;

/**
 * Manages the Typesense products collection: schema, document sync,
 * suggestions and full-text search.
 *
 * Architectural rules followed here:
 *  - All Typesense traffic goes through this service (no direct client use
 *    in controllers/actions).
 *  - All cache logic lives here (CacheHelper with the ['search'] tag).
 */
class ProductSearchService
{
    public const CACHE_TAGS = ['search'];

    protected ?Client $client = null;

    public function isEnabled(): bool
    {
        return config('typesense.enabled', false) && config('typesense.api_key') !== '';
    }

    /**
     * Create the products collection if it does not exist yet, and migrate
     * existing collections by adding any fields that are new in the schema.
     */
    public function ensureCollection(): void
    {
        try {
            $existing = $this->client()->collections[$this->collectionName()]->retrieve();

            $existingFields = array_column($existing['fields'] ?? [], 'name');
            $missing = array_values(array_filter(
                $this->schema()['fields'],
                // `id` is implicit in Typesense and is not reported back by
                // retrieve(), so it must never be part of a schema update.
                fn (array $field) => $field['name'] !== 'id'
                    && ! in_array($field['name'], $existingFields, true)
            ));

            if (! empty($missing)) {
                $this->client()->collections[$this->collectionName()]->update(['fields' => $missing]);
            }
        } catch (ObjectNotFound) {
            $this->client()->collections->create($this->schema());
        }
    }

    /**
     * Drop the products collection entirely (used by search:reindex --fresh).
     */
    public function dropCollection(): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        try {
            $this->client()->collections[$this->collectionName()]->delete();
        } catch (ObjectNotFound) {
            // Collection already absent.
        }
    }

    /**
     * Index (upsert) a single product.
     */
    public function indexProduct(Product $product): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $this->ensureCollection();
        $this->client()
            ->collections[$this->collectionName()]
            ->getDocuments()
            ->upsert($this->buildDocument($product));

        CacheHelper::clearTags(self::CACHE_TAGS);
    }

    /**
     * Remove a product from the index by its database id.
     */
    public function deleteFromIndex(int $productId): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        try {
            $this->client()
                ->collections[$this->collectionName()]
                ->getDocuments()[(string) $productId]
                ->delete();
        } catch (ObjectNotFound) {
            // Already absent from the index — nothing to do.
        }

        CacheHelper::clearTags(self::CACHE_TAGS);
    }

    /**
     * Reindex every non-trashed product in batches. Returns the number of
     * products indexed.
     */
    public function reindexAll(int $chunkSize = 500): int
    {
        if (! $this->isEnabled()) {
            return 0;
        }

        $this->ensureCollection();

        $count = 0;
        $client = $this->client();
        $collection = $client->collections[$this->collectionName()];
        $documents = $collection->getDocuments();

        Product::query()
            ->with($this->indexRelations())
            ->chunkById($chunkSize, function (Collection $products) use ($documents, &$count): void {
                $docs = $products
                    ->map(fn (Product $product) => $this->buildDocument($product))
                    ->values()
                    ->all();

                $results = $documents->import($docs, ['action' => 'upsert']);

                foreach ($results as $result) {
                    if (($result['success'] ?? false) === true) {
                        $count++;

                        continue;
                    }

                    logger()->warning('Typesense import failed for product document', [
                        'id' => $result['document'] ?? null,
                        'error' => $result['error'] ?? 'unknown',
                    ]);
                }
            });

        CacheHelper::clearTags(self::CACHE_TAGS);

        return $count;
    }

    /**
     * Instant, typo-tolerant product suggestions for the search dropdown.
     *
     * @return array<int, array<string, mixed>>
     */
    public function suggest(string $query, int $limit = 8, ?int $categoryId = null): array
    {
        $cacheKey = 'search:suggest:' . md5(mb_strtolower($query) . '|' . ($categoryId ?? 'all') . '|' . $limit);

        return CacheHelper::remember(self::CACHE_TAGS, $cacheKey, (int) config('typesense.suggest_cache_ttl', 300), function () use ($query, $limit, $categoryId) {
            if (! $this->isEnabled()) {
                return $this->fallbackSuggest($query, $limit, $categoryId);
            }

            $filterBy = 'is_active:=true && status:=active';
            if ($categoryId) {
                $filterBy .= " && category_ids:=[{$categoryId}]";
            }

            $response = $this->client()
                ->collections[$this->collectionName()]
                ->getDocuments()
                ->search([
                    'q' => $query,
                    'query_by' => 'name,sku,part_number,model_number,category_names,brand_names,feature_names,tags,summary',
                    'prefix' => true,
                    'filter_by' => $filterBy,
                    'sort_by' => '_text_match:desc',
                    'limit' => $limit,
                    'include_fields' => 'id,uuid,name,slug,route_key,price,sale_price,currency,image_url,brand_names,category_names',
                ]);

            return array_map(
                fn (array $hit) => $this->shapeHit($hit['document'] ?? []),
                $response['hits'] ?? []
            );
        });
    }

    /**
     * Full-text product search with filters, facets and sorting.
     *
     * @param  array<string, mixed>  $params  query, filters (category_ids, brand_ids,
     *                                        min_price, max_price), sort, page, per_page
     * @return array<string, mixed>  Normalized response: 'hits', 'facets', 'found', 'page', 'per_page'
     */
    public function search(array $params = []): array
    {
        $query = trim((string) data_get($params, 'query', ''));
        $filters = data_get($params, 'filters', []);
        $sort = (string) data_get($params, 'sort', 'newest');
        $page = max(1, (int) data_get($params, 'page', 1));
        $perPage = min(48, max(1, (int) data_get($params, 'per_page', 12)));

        if (! $this->isEnabled()) {
            throw new TypesenseClientError('Typesense is not enabled.');
        }

        $filterBy = 'is_active:=true && status:=active';

        $categoryIds = $this->intList(data_get($filters, 'category_ids'));
        if (! empty($categoryIds)) {
            $filterBy .= ' && category_ids:[' . implode(',', $categoryIds) . ']';
        }

        $brandIds = $this->intList(data_get($filters, 'brand_ids'));
        if (! empty($brandIds)) {
            $filterBy .= ' && brand_ids:[' . implode(',', $brandIds) . ']';
        }

        $featureIds = $this->intList(data_get($filters, 'feature_ids'));
        if (! empty($featureIds)) {
            $filterBy .= ' && feature_ids:[' . implode(',', $featureIds) . ']';
        }

        $minPrice = data_get($filters, 'min_price');
        if (is_numeric($minPrice)) {
            $filterBy .= ' && price:>=' . (float) $minPrice;
        }

        $maxPrice = data_get($filters, 'max_price');
        if (is_numeric($maxPrice)) {
            $filterBy .= ' && price:<=' . (float) $maxPrice;
        }

        $response = $this->client()
            ->collections[$this->collectionName()]
            ->getDocuments()
            ->search([
                'q' => $query !== '' ? $query : '*',
                'query_by' => 'name,sku,part_number,model_number,category_names,brand_names,tags,summary,description',
                'filter_by' => $filterBy,
                'facet_by' => 'category_names,brand_names,feature_names',
                'max_facet_values' => 25,
                'sort_by' => $this->sortExpression($query, $sort),
                'page' => $page,
                'per_page' => $perPage,
                'include_fields' => 'id,uuid,name,slug,route_key,price,sale_price,currency,image_url,brand_names,category_names,summary',
            ]);

        return [
            'hits' => array_map(
                fn (array $hit) => $this->shapeHit($hit['document'] ?? []),
                $response['hits'] ?? []
            ),
            'facets' => $this->normalizeFacets($response['facet_counts'] ?? []),
            'found' => (int) ($response['found'] ?? 0),
            'page' => $page,
            'per_page' => $perPage,
            'search_time_ms' => (int) ($response['search_time_ms'] ?? 0),
        ];
    }

    /**
     * Fallback used when Typesense is disabled/unreachable so the dropdown
     * keeps working from the database (same behaviour as before).
     *
     * @return array<int, array<string, mixed>>
     */
    public function fallbackSuggest(string $query, int $limit = 8, ?int $categoryId = null): array
    {
        return Product::query()
            ->where('is_active', true)
            ->where('status', 'active')
            ->with($this->indexRelations())
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('sku', 'like', "%{$query}%")
                    ->orWhere('part_number', 'like', "%{$query}%")
                    ->orWhere('model_number', 'like', "%{$query}%");
            })
            ->when($categoryId, fn ($q) => $q->whereHas('categories', fn ($c) => $c->whereKey($categoryId)))
            ->limit($limit)
            ->get()
            ->map(fn (Product $product) => $this->shapeDocument($product))
            ->values()
            ->all();
    }

    /**
     * Build the denormalized document stored in Typesense.
     *
     * @return array<string, mixed>
     */
    public function buildDocument(Product $product): array
    {
        $product->loadMissing($this->indexRelations());

        $price = data_get($product->metadata, 'price');
        $salePrice = data_get($product->metadata, 'sale_price');

        return [
            'id' => (string) $product->id,
            'uuid' => (string) $product->uuid,
            'name' => (string) $product->name,
            'slug' => (string) $product->slug,
            'route_key' => $product->slug . '-' . substr((string) $product->uuid, 0, 8),
            'summary' => (string) $product->summary,
            'description' => (string) $product->description,
            'sku' => (string) $product->sku,
            'part_number' => (string) $product->part_number,
            'model_number' => (string) $product->model_number,
            'category_names' => $product->categories->pluck('name')->filter()->values()->all(),
            'category_ids' => $product->categories->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
            'brand_names' => $product->brands->pluck('name')->filter()->values()->all(),
            'brand_ids' => $product->brands->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
            'feature_names' => $product->features->pluck('name')->filter()->values()->all(),
            'feature_ids' => $product->features->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
            'tags' => $product->tags->pluck('name')->filter()->values()->all(),
            'price' => $price !== null && $price !== '' ? (float) $price : 0.0,
            'sale_price' => $salePrice !== null && $salePrice !== '' ? (float) $salePrice : 0.0,
            'currency' => (string) data_get($product->metadata, 'currency', 'USD'),
            'image_url' => $this->resolveImageUrl($product),
            'is_active' => (bool) $product->is_active,
            'status' => (string) $product->status,
            'popularity' => 0,
            'created_at' => $product->created_at?->getTimestamp() ?? time(),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    protected function indexRelations(): array
    {
        return [
            'categories',
            'brands',
            'features',
            'tags',
            'dams' => fn ($query) => $query
                ->where('collection_name', 'featured')
                ->orderByRaw('CASE WHEN sort_order IS NULL THEN 1 ELSE 0 END')
                ->orderBy('sort_order'),
        ];
    }

    protected function resolveImageUrl(Product $product): ?string
    {
        $featured = $product->dams->first();

        if (! $featured || ! $featured->object_key) {
            return null;
        }

        $key = data_get($featured->metadata, 'variants.medium', $featured->object_key);

        return config('app.cdn_url', 'https://cdn.htashop.com') . '/' . ltrim((string) $key, '/');
    }

    /**
     * Shape a Typesense hit/document into the public suggestion payload.
     *
     * @param  array<string, mixed>  $doc
     * @return array<string, mixed>
     */
    protected function shapeHit(array $doc): array
    {
        $brandNames = $doc['brand_names'] ?? [];
        $categoryNames = $doc['category_names'] ?? [];

        return [
            'id' => $doc['id'] ?? null,
            'uuid' => $doc['uuid'] ?? null,
            'name' => $doc['name'] ?? '',
            'slug' => $doc['slug'] ?? '',
            'route_key' => $doc['route_key'] ?? '',
            'price' => $doc['price'] ?? null,
            'sale_price' => $doc['sale_price'] ?? null,
            'currency' => $doc['currency'] ?? 'USD',
            'image_url' => $doc['image_url'] ?? null,
            'summary' => $doc['summary'] ?? null,
            'brands' => array_values(array_map(fn ($name) => ['name' => (string) $name], (array) $brandNames)),
            'categories' => array_values(array_map(fn ($name) => ['name' => (string) $name], (array) $categoryNames)),
        ];
    }

    /**
     * Shape a fresh Product model into the same public suggestion payload.
     *
     * @return array<string, mixed>
     */
    protected function shapeDocument(Product $product): array
    {
        $doc = $this->buildDocument($product);

        return [
            'id' => $doc['id'],
            'uuid' => $doc['uuid'],
            'name' => $doc['name'],
            'slug' => $doc['slug'],
            'route_key' => $doc['route_key'],
            'price' => $doc['price'],
            'sale_price' => $doc['sale_price'],
            'currency' => $doc['currency'],
            'image_url' => $doc['image_url'],
            'summary' => $doc['summary'] !== '' ? $doc['summary'] : null,
            'brands' => array_map(fn ($name) => ['name' => (string) $name], $doc['brand_names']),
            'categories' => array_map(fn ($name) => ['name' => (string) $name], $doc['category_names']),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $facetCounts
     * @return array<string, mixed>
     */
    protected function normalizeFacets(array $facetCounts): array
    {
        $facets = [];

        foreach ($facetCounts as $facet) {
            $field = $facet['field_name'] ?? null;
            if (! $field) {
                continue;
            }

            $facets[$field] = [
                'counts' => array_map(
                    fn (array $count) => [
                        'value' => $count['value'] ?? null,
                        'count' => (int) ($count['count'] ?? 0),
                    ],
                    $facet['counts'] ?? []
                ),
                'stats' => $facet['stats'] ?? null,
            ];
        }

        return $facets;
    }

    protected function sortExpression(string $query, string $sort): string
    {
        return match ($sort) {
            'price_asc' => 'price:asc,created_at:desc',
            'price_desc' => 'price:desc,created_at:desc',
            'name' => 'name:asc,created_at:desc',
            'best_sellers', 'trending' => 'popularity:desc,created_at:desc',
            default => $query !== '' ? '_text_match:desc' : 'created_at:desc',
        };
    }

    /**
     * @return array<int, int>
     */
    protected function intList(mixed $value): array
    {
        if (is_array($value)) {
            $list = $value;
        } elseif (is_string($value) && trim($value) !== '') {
            $list = explode(',', $value);
        } else {
            $list = [];
        }

        return array_values(array_unique(array_filter(array_map('intval', $list))));
    }

    protected function collectionName(): string
    {
        return (string) config('typesense.products_collection', 'products');
    }

    protected function client(): Client
    {
        if ($this->client instanceof Client) {
            return $this->client;
        }

        return $this->client = new Client([
            'api_key' => (string) config('typesense.api_key'),
            'nodes' => [[
                'host' => (string) config('typesense.host'),
                'port' => (string) config('typesense.port'),
                'protocol' => (string) config('typesense.protocol'),
            ]],
            'connection_timeout_seconds' => (float) config('typesense.connect_timeout_seconds', 3),
            'healthcheck_interval_seconds' => (int) config('typesense.healthcheck_interval_seconds', 60),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function schema(): array
    {
        return [
            'name' => $this->collectionName(),
            'fields' => [
                ['name' => 'id', 'type' => 'string'],
                ['name' => 'uuid', 'type' => 'string'],
                ['name' => 'name', 'type' => 'string'],
                ['name' => 'slug', 'type' => 'string'],
                ['name' => 'summary', 'type' => 'string', 'optional' => true],
                ['name' => 'description', 'type' => 'string', 'optional' => true],
                ['name' => 'sku', 'type' => 'string', 'optional' => true],
                ['name' => 'part_number', 'type' => 'string', 'optional' => true],
                ['name' => 'model_number', 'type' => 'string', 'optional' => true],
                ['name' => 'route_key', 'type' => 'string'],
                ['name' => 'category_names', 'type' => 'string[]', 'facet' => true],
                ['name' => 'category_ids', 'type' => 'int32[]'],
                ['name' => 'brand_names', 'type' => 'string[]', 'facet' => true],
                ['name' => 'brand_ids', 'type' => 'int32[]'],
                ['name' => 'feature_names', 'type' => 'string[]', 'facet' => true],
                ['name' => 'feature_ids', 'type' => 'int32[]'],
                ['name' => 'tags', 'type' => 'string[]'],
                ['name' => 'price', 'type' => 'float', 'facet' => true],
                ['name' => 'sale_price', 'type' => 'float', 'optional' => true],
                ['name' => 'currency', 'type' => 'string'],
                ['name' => 'image_url', 'type' => 'string', 'optional' => true],
                ['name' => 'is_active', 'type' => 'bool'],
                ['name' => 'status', 'type' => 'string'],
                ['name' => 'popularity', 'type' => 'int32'],
                ['name' => 'created_at', 'type' => 'int64'],
            ],
            'default_sorting_field' => 'created_at',
        ];
    }
}
