<?php

namespace App\Services\Audit;

use App\Models\Audit;
use App\Models\User;

class AuditService
{
    public function __construct(
        protected AuditLogger $logger,
    ) {}

    /**
     * Record an audit event.
     *
     * @param  User|null  $actor
     * @param  string  $event
     * @param  mixed  $auditable  Model, array with ['type' => 'Class', 'id' => 123], or class name
     * @param  array  $meta  Legacy meta keys plus reason/changed_columns/tenant_id
     */
    public function record(?User $actor, string $event, $auditable = null, array $meta = []): Audit
    {
        return $this->logger->record($actor, $event, $auditable, $meta);
    }

    /**
     * Get comprehensive admin statistics and analytics.
     *
     * @param array $filters Optional filters (start_date, end_date)
     * @return array
     */
    public function getAdminStats(array $filters = []): array
    {
        $query = Audit::query();

        // Apply date range filter if provided
        if (!empty($filters['start_date'])) {
            $query->where('occurred_at', '>=', $filters['start_date']);
        }
        if (!empty($filters['end_date'])) {
            $query->where('occurred_at', '<=', $filters['end_date']);
        }

        // Get basic counts
        $total = $query->count();

        // Get counts by event type
        $byEvent = (clone $query)
            ->selectRaw('event, COUNT(*) as count')
            ->whereNotNull('event')
            ->groupBy('event')
            ->orderByDesc('count')
            ->get()
            ->pluck('count', 'event')
            ->toArray();

        // Get counts by auditable type
        $byauditableType = (clone $query)
            ->selectRaw('auditable_type, COUNT(*) as count')
            ->whereNotNull('auditable_type')
            ->groupBy('auditable_type')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->mapWithKeys(function ($item) {
                // Extract short class name
                $shortName = $item->auditable_type ? class_basename($item->auditable_type) : 'Unknown';
                return [$shortName => $item->count];
            })
            ->toArray();

        // Get trend data (last 30 days)
        $trendData = (clone $query)
            ->selectRaw('DATE(occurred_at) as date, COUNT(*) as count')
            ->where('occurred_at', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(function ($item) {
                return [
                    'date' => $item->date,
                    'count' => $item->count,
                ];
            })
            ->toArray();

        // Get recent audits
        $recentAudits = (clone $query)
            ->select(['id', 'user_id', 'actor_type', 'actor_id', 'event', 'auditable_type', 'auditable_id', 'description', 'ip_hash', 'occurred_at'])
            ->orderByDesc('occurred_at')
            ->limit(10)
            ->get()
            ->map(function ($audit) {
                return [
                    'id' => $audit->id,
                    'user_id' => $audit->user_id,
                    'event' => $audit->event,
                    'auditable_type' => $audit->auditable_type ? class_basename($audit->auditable_type) : null,
                    'auditable_id' => $audit->auditable_id,
                    'description' => $audit->description,
                    'ip_hash' => $audit->ip_hash,
                    'occurred_at' => $audit->occurred_at,
                ];
            })
            ->toArray();

        // Get unique actors count
        $uniqueActors = (clone $query)
            ->whereNotNull('user_id')
            ->distinct('user_id')
            ->count('user_id');

        // Get top actors
        $topActors = (clone $query)
            ->selectRaw('user_id, COUNT(*) as count')
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->orderByDesc('count')
            ->limit(5)
            ->get()
            ->pluck('count', 'user_id')
            ->toArray();

        // Count audits by hour of day (for activity pattern)
        $byHourOfDay = (clone $query)
            ->selectRaw('HOUR(occurred_at) as hour, COUNT(*) as count')
            ->where('occurred_at', '>=', now()->subDays(7))
            ->groupBy('hour')
            ->orderBy('hour')
            ->get()
            ->pluck('count', 'hour')
            ->toArray();

        return [
            'totals' => [
                'total' => $total,
                'unique_actors' => $uniqueActors,
                'last_24h' => (clone $query)->where('occurred_at', '>=', now()->subDay())->count(),
                'last_7d' => (clone $query)->where('occurred_at', '>=', now()->subDays(7))->count(),
            ],
            'by_event' => $byEvent,
            'by_auditable_type' => $byauditableType,
            'by_hour_of_day' => $byHourOfDay,
            'top_actors' => $topActors,
            'trend_data' => $trendData,
            'recent_audits' => $recentAudits,
        ];
    }
}
