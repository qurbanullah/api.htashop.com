<?php

namespace App\Services\Search;

use App\Helpers\CacheHelper;
use App\Models\SearchQuery;
use App\Services\Search\ProductSearchService;

/**
 * Records every search action (suggestion keystroke, results load, product
 * click) into search_queries so we can power trending searches, zero-result
 * reports and query→click→purchase analytics.
 */
class SearchAnalyticsService
{
    /**
     * Record a search query. Suggest keystrokes are debounced: a single
     * session reuses the same suggest row for up to 10 minutes, while every
     * deliberate results-page load creates its own row.
     */
    public function recordQuery(
        string $query,
        string $source,
        int $resultsCount,
        ?array $filters = null,
        ?string $sessionId = null,
        ?int $userId = null,
    ): SearchQuery {
        $normalized = $this->normalize($query);

        if ($source === 'suggest' && $sessionId) {
            $existing = SearchQuery::query()
                ->where('session_id', $sessionId)
                ->where('normalized_query', $normalized)
                ->where('source', 'suggest')
                ->where('created_at', '>=', now()->subMinutes(10))
                ->latest()
                ->first();

            if ($existing) {
                $existing->update([
                    'results_count' => $resultsCount,
                ]);

                return $existing;
            }
        }

        return SearchQuery::create([
            'query' => $query,
            'normalized_query' => $normalized,
            'user_id' => $userId,
            'session_id' => $sessionId,
            'source' => $source,
            'results_count' => $resultsCount,
            'is_zero_result' => $resultsCount === 0,
            'filters' => $filters,
        ]);
    }

    /**
     * Attach a product click to the most recent matching query row for the
     * session. Falls back to creating a minimal "results" row so the click is
     * never lost even when the query happened before tracking was enabled.
     */
    public function recordClickForQuery(string $query, int $productId, ?string $sessionId = null): void
    {
        $normalized = $this->normalize($query);

        $row = SearchQuery::query()
            ->when($sessionId, fn ($q) => $q->where('session_id', $sessionId))
            ->where('normalized_query', $normalized)
            ->where('created_at', '>=', now()->subMinutes(30))
            ->latest()
            ->first();

        if (! $row) {
            $row = SearchQuery::create([
                'query' => $query,
                'normalized_query' => $normalized,
                'session_id' => $sessionId,
                'source' => 'results',
                'results_count' => 0,
                'is_zero_result' => false,
            ]);
        }

        $row->update([
            'clicked_product_id' => $productId,
            'clicked_at' => now(),
        ]);
    }

    /**
     * Top searched terms over the last 30 days (excluding zero-result queries
     * and the "*" browse-all marker). Cached for 15 minutes.
     *
     * @return array<int, array{query: string, count: int}>
     */
    public function trending(int $limit = 10): array
    {
        $limit = min(20, max(1, $limit));

        return CacheHelper::remember(ProductSearchService::CACHE_TAGS, "search:trending:{$limit}", 900, function () use ($limit) {
            return SearchQuery::query()
                ->where('source', 'results')
                ->where('is_zero_result', false)
                ->where('results_count', '>', 0)
                ->where('created_at', '>=', now()->subDays(30))
                ->whereNotNull('normalized_query')
                ->where('normalized_query', '!=', '')
                ->where('normalized_query', '!=', '*')
                ->selectRaw('normalized_query, MAX(query) AS latest_query, COUNT(*) AS cnt')
                ->groupBy('normalized_query')
                ->orderByDesc('cnt')
                ->limit($limit)
                ->get()
                ->map(fn ($row) => [
                    'query' => $row->latest_query ?: $row->normalized_query,
                    'count' => (int) $row->cnt,
                ])
                ->values()
                ->all();
        });
    }

    protected function normalize(string $query): string
    {
        $query = mb_strtolower(trim($query));

        return preg_replace('/\s+/u', ' ', $query) ?? $query;
    }
}
