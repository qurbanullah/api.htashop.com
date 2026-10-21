<?php

namespace App\Services\Search;

use App\Helpers\CacheHelper;
use App\Http\Middleware\AssignRequestId;
use App\Jobs\Analytics\RecordEvent;
use App\Models\SearchQuery;
use App\Services\Search\ProductSearchService;
use App\Support\Observability\IpHasher;
use App\Support\Tenant\TenantContext;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Records search intent as analytics events, not as one OLTP row per keystroke.
 *
 * The durable rollup is `analytics_daily` (written by `analytics:flush`); the
 * legacy `search_queries` table is only read as a fallback for historical data.
 */
class SearchAnalyticsService
{
    /**
     * Record a search query. Suggest keystrokes are de-duplicated for ten
     * minutes per session, so typing "laptop" does not create eight rows.
     */
    public function recordQuery(
        string $query,
        string $source,
        int $resultsCount,
        ?array $filters = null,
        ?string $sessionId = null,
        ?int $userId = null,
        bool $consent = true,
    ): ?SearchQuery {
        $normalized = $this->normalize($query);

        if (! $consent) {
            return null;
        }

        if ($source === 'suggest' && $sessionId !== null) {
            $key = 'analytics:search:suggest:'.$sessionId.':'.sha1($normalized);
            $ttl = now()->addMinutes(max(1, (int) config('analytics.search_suggest_dedupe_minutes', 10)));

            if (! Cache::add($key, 1, $ttl)) {
                return null;
            }
        }

        RecordEvent::dispatch($this->payload(
            eventName: 'search',
            sessionId: $sessionId,
            userId: $userId,
            consent: $consent,
            context: [
                'search' => [
                    'query' => $query !== '' ? $query : '*',
                    'results_count' => $resultsCount,
                    'zero_result' => $resultsCount === 0,
                    'source' => $source,
                ],
            ],
        ));

        return null;
    }

    /**
     * Attach a product click to the search intent.
     */
    public function recordClickForQuery(
        string $query,
        int $productId,
        ?string $sessionId = null,
        ?int $userId = null,
        bool $consent = true,
    ): void {
        if (! $consent) {
            return;
        }

        RecordEvent::dispatch($this->payload(
            eventName: 'search_click',
            sessionId: $sessionId,
            userId: $userId,
            consent: $consent,
            context: [
                'search' => [
                    'query' => $query,
                    'source' => 'results',
                ],
                'product' => ['id' => $productId],
            ],
        ));
    }

    /**
     * Top searched terms over the last 30 days, from the daily rollup.
     * Falls back to the legacy `search_queries` table for older deployments.
     *
     * @return array<int, array{query: string, count: int}>
     */
    public function trending(int $limit = 10): array
    {
        $limit = min(20, max(1, $limit));
        $tenantId = TenantContext::publicTenantId() ?? 0;

        return CacheHelper::remember(
            ProductSearchService::CACHE_TAGS,
            "search:trending:{$tenantId}:{$limit}",
            900,
            function () use ($limit, $tenantId) {
                $rows = DB::table('analytics_daily')
                    ->where('tenant_id', $tenantId)
                    ->where('entity_type', 'search')
                    ->where('metric_name', 'search')
                    ->where('metric_date', '>=', now()->subDays(30)->toDateString())
                    ->selectRaw('entity_id, MAX(label) AS query, SUM(count) AS cnt')
                    ->groupBy('entity_id')
                    ->orderByDesc('cnt')
                    ->limit($limit)
                    ->get();

                if ($rows->isNotEmpty()) {
                    return $rows
                        ->map(fn ($row) => [
                            'query' => $row->query ?: $row->entity_id,
                            'count' => (int) $row->cnt,
                        ])
                        ->values()
                        ->all();
                }

                // Historical fallback (pre-rollup data).
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
            },
        );
    }

    protected function normalize(string $query): string
    {
        $query = mb_strtolower(trim($query));

        return preg_replace('/\s+/u', ' ', $query) ?? $query;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function payload(string $eventName, ?string $sessionId, ?int $userId, bool $consent, array $context): array
    {
        $request = app()->runningInConsole() ? null : request();

        return [
            'event_name' => $eventName,
            'schema_version' => 1,
            'occurred_at' => now()->toIso8601String(),
            'source' => 'web',
            'session_id' => $sessionId,
            'anonymous_id' => null,
            'user_id' => $userId,
            'tenant_id' => TenantContext::publicTenantId(),
            'request_id' => AssignRequestId::fromRequest(),
            'ip_hash' => $request ? IpHasher::hash($request->ip()) : null,
            'user_agent' => $request?->userAgent() ? Str::limit($request->userAgent(), 512, '') : null,
            'bot' => false,
            'context' => $context + ['consent' => ['analytics' => $consent]],
        ];
    }
}
