<?php

namespace App\Services\Audit;

use App\Models\Audit;
use App\Models\User;
use Illuminate\Http\Request;

class AuditService
{
    /**
     * Record an audit event
     *
     * @param User|null $actor
     * @param string $action
     * @param mixed $auditable  Model or array with ['type' => 'Class', 'id' => 123] or null
     * @param array $meta
     */
    public function record(?User $actor, string $event, $auditable = null, array $meta = []): Audit
    {
        $actorId = $actor?->id ?? null;

        $auditableType = null;
        $auditableId = null;

        if (is_object($auditable) && method_exists($auditable, 'getKey')) {
            $auditableType = get_class($auditable);
            $auditableId = $auditable->getKey();
        } elseif (is_array($auditable) && isset($auditable['type'])) {
            $auditableType = $auditable['type'];
            $auditableId = $auditable['id'] ?? null;
        } elseif (is_string($auditable)) {
            $auditableType = $auditable;
        }

        // Capture request context if available
        $ip_address = null;
        $userAgent = null;
        if (function_exists('request')) {
            try {
                $req = request();
                if ($req instanceof Request) {
                    $ip_address = $req->ip();
                    $userAgent = $req->userAgent();
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        // Map meta keys to dedicated columns when present
        $oldValues = $meta['old_values'] ?? null;
        $newValues = $meta['new_values'] ?? null;
        $auditableTypeName = $meta['auditable_type_name'] ?? null;
        $description = $meta['description'] ?? null;

        // Prefer explicit event parameter, fall back to meta.event
        $eventValue = $event ?: ($meta['event'] ?? null);

        $audit = Audit::create([
            'user_id' => $actorId,
            'event' => $eventValue,
            'auditable_type' => $auditableType,
            'auditable_id' => $auditableId,
            'auditable_type_name' => $auditableTypeName,
            'meta' => $meta ?: null,
            'old_values' => $oldValues ?: null,
            'new_values' => $newValues ?: null,
            'ip_address' => $ip_address,
            'user_agent' => $userAgent,
            'description' => $description,
        ]);

        return $audit;
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
            $query->where('created_at', '>=', $filters['start_date']);
        }
        if (!empty($filters['end_date'])) {
            $query->where('created_at', '<=', $filters['end_date']);
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
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->where('created_at', '>=', now()->subDays(30))
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
            ->select(['id', 'user_id', 'event', 'auditable_type', 'auditable_id', 'description', 'ip_address', 'created_at'])
            ->orderByDesc('created_at')
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
                    'ip_address' => $audit->ip_address,
                    'created_at' => $audit->created_at,
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
            ->selectRaw('HOUR(created_at) as hour, COUNT(*) as count')
            ->where('created_at', '>=', now()->subDays(7))
            ->groupBy('hour')
            ->orderBy('hour')
            ->get()
            ->pluck('count', 'hour')
            ->toArray();

        return [
            'totals' => [
                'total' => $total,
                'unique_actors' => $uniqueActors,
                'last_24h' => (clone $query)->where('created_at', '>=', now()->subDay())->count(),
                'last_7d' => (clone $query)->where('created_at', '>=', now()->subDays(7))->count(),
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
