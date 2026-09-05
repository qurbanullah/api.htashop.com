<?php

namespace App\Http\Controllers\V1\Search;

use App\Http\Controllers\Controller;
use App\Http\Responses\V1\ApiResponse;
use App\Services\Search\ProductSearchService;
use App\Services\Search\SearchAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;
use Typesense\Exceptions\TypesenseClientError;

class SearchController extends Controller
{
    public function __construct(
        protected ProductSearchService $searchService,
        protected SearchAnalyticsService $analytics,
    ) {
    }

    /**
     * Instant typeahead suggestions for the search dropdown.
     */
    public function suggest(Request $request): JsonResponse
    {
        $query = $request->string('q')->trim()->toString();

        if (mb_strlen($query) < 2) {
            return ApiResponse::success(['data' => []], 'Suggestions retrieved successfully');
        }

        $categoryId = $request->integer('category_id') ?: null;
        $limit = min(10, max(1, (int) $request->integer('limit', config('typesense.suggest_limit', 8))));
        $sessionId = $this->sessionId($request);

        try {
            $suggestions = $this->searchService->suggest($query, $limit, $categoryId);
        } catch (TypesenseClientError|Throwable $e) {
            Log::warning("Search suggest fallback to database: {$e->getMessage()}");
            $suggestions = $this->searchService->fallbackSuggest($query, $limit, $categoryId);
        }

        $this->analytics->recordQuery(
            $query,
            'suggest',
            count($suggestions),
            $categoryId ? ['category_id' => $categoryId] : null,
            $sessionId,
            auth()->id(),
        );

        return ApiResponse::success(['data' => $suggestions], 'Suggestions retrieved successfully');
    }

    /**
     * Full-text product search with filters, facets and sorting.
     */
    public function index(Request $request): JsonResponse
    {
        $query = $request->string('q')->trim()->toString();
        $filters = [
            'category_ids' => $this->arrayParam($request, 'category_ids'),
            'brand_ids' => $this->arrayParam($request, 'brand_ids'),
            'feature_ids' => $this->arrayParam($request, 'feature_ids'),
            'min_price' => $request->input('min_price'),
            'max_price' => $request->input('max_price'),
        ];
        $sort = $request->string('sort', 'newest')->toString();
        $page = max(1, (int) $request->integer('page', 1));
        $perPage = min(48, max(1, (int) $request->integer('per_page', 12)));
        $sessionId = $this->sessionId($request);

        try {
            $result = $this->searchService->search([
                'query' => $query,
                'filters' => $filters,
                'sort' => $sort,
                'page' => $page,
                'per_page' => $perPage,
            ]);
        } catch (TypesenseClientError|Throwable $e) {
            Log::warning("Search unavailable, serving catalog fallback: {$e->getMessage()}");

            return $this->fallbackCatalogResponse($request, $query, $filters, $sort, $page, $perPage, $sessionId);
        }

        $this->analytics->recordQuery(
            $query !== '' ? $query : '*',
            'results',
            $result['found'],
            $filters,
            $sessionId,
            auth()->id(),
        );

        return ApiResponse::success([
            'data' => $result['hits'],
            'facets' => $result['facets'],
            'meta' => [
                'current_page' => $result['page'],
                'last_page' => $result['per_page'] > 0 ? (int) ceil($result['found'] / $result['per_page']) : 1,
                'per_page' => $result['per_page'],
                'total' => $result['found'],
                'from' => $result['found'] === 0 ? null : (($result['page'] - 1) * $result['per_page'] + 1),
                'to' => min($result['found'], $result['page'] * $result['per_page']) ?: null,
                'search_time_ms' => $result['search_time_ms'],
            ],
        ], 'Search completed successfully');
    }

    /**
     * Trending search terms (from search_queries analytics).
     */
    public function trending(Request $request): JsonResponse
    {
        $limit = min(20, max(1, (int) $request->integer('limit', 10)));

        return ApiResponse::success(
            ['data' => $this->analytics->trending($limit)],
            'Trending searches retrieved successfully',
        );
    }

    /**
     * Record that a user clicked a product from search results.
     */
    public function click(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'max:120'],
            'product_id' => ['required', 'integer'],
            'session_id' => ['nullable', 'string', 'max:120'],
        ]);

        $this->analytics->recordClickForQuery(
            $validated['q'],
            (int) $validated['product_id'],
            $validated['session_id'] ?? null,
        );

        return ApiResponse::success(null, 'Click recorded successfully');
    }

    /**
     * DB-backed search used when Typesense is unreachable — keeps the
     * storefront results page functional.
     */
    private function fallbackCatalogResponse(
        Request $request,
        string $query,
        array $filters,
        string $sort,
        int $page,
        int $perPage,
        ?string $sessionId,
    ): JsonResponse {
        $products = app(\App\Services\Catalog\CatalogService::class)->products([
            'search' => $query,
            'category_ids' => $filters['category_ids'],
            'brand_ids' => $filters['brand_ids'],
            'feature_ids' => $filters['feature_ids'] ?? [],
            'min_price' => $filters['min_price'],
            'max_price' => $filters['max_price'],
            'sort' => $sort,
            'per_page' => $perPage,
            'page' => $page,
        ]);

        $this->analytics->recordQuery(
            $query !== '' ? $query : '*',
            'results',
            $products->total(),
            $filters,
            $sessionId,
            auth()->id(),
        );

        $items = \App\Http\Resources\V1\Catalog\CatalogProductResource::collection($products->items())->resolve();

        return ApiResponse::success([
            'data' => $items,
            'facets' => [],
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
                'from' => $products->firstItem(),
                'to' => $products->lastItem(),
                'search_time_ms' => 0,
                'fallback' => true,
            ],
        ], 'Search completed successfully');
    }

    private function arrayParam(Request $request, string $key): array
    {
        $value = $request->input($key);

        if (is_array($value)) {
            return array_values(array_filter(array_map('intval', $value)));
        }

        if (is_string($value) && trim($value) !== '') {
            return array_values(array_filter(array_map('intval', explode(',', $value))));
        }

        return [];
    }

    private function sessionId(Request $request): ?string
    {
        return $request->string('session_id')->trim()->toString() ?: null;
    }
}
