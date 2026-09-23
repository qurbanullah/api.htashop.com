<?php

declare(strict_types=1);

namespace App\Services\Ai\Tools;

use App\Interfaces\Ai\ChatToolInterface;
use App\Services\Search\ProductSearchService;
use App\Support\Ai\ChatToolContext;
use Throwable;

/**
 * Looks up catalogue products from the visitor's own words.
 *
 * Prices come from the live search index (or the database fallback) so the
 * model never has to recall them. Stock levels and delivery dates are
 * deliberately not exposed: there is no reliable source for them here, and
 * inventing them would be worse than saying nothing.
 */
class SearchProductsTool implements ChatToolInterface
{
    public function __construct(
        protected ProductSearchService $productSearchService,
    ) {}

    public function name(): string
    {
        return 'search_products';
    }

    public function description(): string
    {
        return 'Search the HTAShop catalogue for products matching a description or part number. '
            .'Returns names, current prices and links. It does not provide stock levels or '
            .'delivery dates, so never state those.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => [
                    'type' => 'string',
                    'description' => 'What the visitor is looking for, in their own words.',
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Maximum number of products to return (1-10).',
                ],
            ],
            'required' => ['query'],
        ];
    }

    public function handle(array $arguments, ChatToolContext $context): array
    {
        if (! config('ai.chat.products_enabled', true)) {
            return ['error' => 'Product search is currently disabled.'];
        }

        $query = trim((string) ($arguments['query'] ?? ''));

        if ($query === '') {
            return ['products' => []];
        }

        $limit = max(1, min(10, (int) ($arguments['limit'] ?? 5)));

        try {
            $hits = $this->productSearchService->suggest($query, $limit);
        } catch (Throwable $exception) {
            // Typesense is enabled but unreachable — keep the tool useful.
            logger()->warning('Product search tool fell back to the database', [
                'error' => $exception->getMessage(),
            ]);

            $hits = $this->productSearchService->fallbackSuggest($query, $limit);
        }

        return [
            'products' => array_map(fn (array $hit) => [
                'name' => (string) ($hit['name'] ?? ''),
                'url' => ! empty($hit['route_key']) ? '/products/'.$hit['route_key'] : null,
                'price' => $hit['price'] ?? null,
                'sale_price' => $hit['sale_price'] ?? null,
                'currency' => $hit['currency'] ?? null,
                'summary' => $hit['summary'] ?? null,
                'brand' => data_get($hit, 'brands.0.name'),
            ], $hits),
        ];
    }
}
