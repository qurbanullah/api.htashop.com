<?php

declare(strict_types=1);

namespace App\Http\Controllers\V1\Observability;

use App\Contracts\Analytics\AnalyticsBuffer;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditExport;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Prometheus scrape endpoint.
 *
 * Business gauges are cached for a minute; request counters come from Redis.
 * No high-cardinality label is ever emitted (no order/product/user ids).
 */
class MetricsController extends Controller
{
    private const PAID_STATUSES = [
        OrderStatus::CONFIRMED,
        OrderStatus::PROCESSING,
        OrderStatus::AWAITING_SOURCING,
        OrderStatus::IN_TRANSIT,
        OrderStatus::IN_CUSTOMS,
        OrderStatus::ARRIVED_WAREHOUSE,
        OrderStatus::SHIPPED,
        OrderStatus::DELIVERED,
    ];

    public function __invoke(Request $request, AnalyticsBuffer $buffer): Response
    {
        if (! config('observability.metrics.enabled', true)) {
            abort(404);
        }

        $token = (string) config('observability.metrics.token', '');

        if ($token !== '') {
            $provided = $request->bearerToken() ?: (string) $request->header('X-Metrics-Token', '');

            if ($provided === '' || ! hash_equals($token, $provided)) {
                abort(403);
            }
        }

        $cacheSeconds = max(5, (int) config('observability.metrics.cache_seconds', 60));

        $business = Cache::remember('metrics:business', $cacheSeconds, fn (): array => $this->businessMetrics($buffer));

        $lines = ['# HELP htashop_up Whether the API metrics endpoint is responding.', '# TYPE htashop_up gauge', 'htashop_up 1'];

        $lines[] = '# HELP htashop_orders_today Orders created today by status.';
        $lines[] = '# TYPE htashop_orders_today gauge';
        foreach ($business['orders_today_by_status'] as $status => $count) {
            $lines[] = 'htashop_orders_today{status="'.$this->label((string) $status).'"} '.$count;
        }

        $lines[] = '# HELP htashop_orders_yesterday Orders created yesterday (paid statuses).';
        $lines[] = '# TYPE htashop_orders_yesterday gauge';
        $lines[] = 'htashop_orders_yesterday '.$business['orders_yesterday'];

        $lines[] = '# HELP htashop_revenue_today_pkr Sum of today\'s paid orders.';
        $lines[] = '# TYPE htashop_revenue_today_pkr gauge';
        $lines[] = 'htashop_revenue_today_pkr '.$business['revenue_today'];

        $lines[] = '# HELP htashop_revenue_yesterday_pkr Sum of yesterday\'s paid orders.';
        $lines[] = '# TYPE htashop_revenue_yesterday_pkr gauge';
        $lines[] = 'htashop_revenue_yesterday_pkr '.$business['revenue_yesterday'];

        $lines[] = '# HELP htashop_aov_today_pkr Average order value today.';
        $lines[] = '# TYPE htashop_aov_today_pkr gauge';
        $lines[] = 'htashop_aov_today_pkr '.$business['aov_today'];

        $lines[] = '# HELP htashop_pending_payments Payments still pending.';
        $lines[] = '# TYPE htashop_pending_payments gauge';
        $lines[] = 'htashop_pending_payments '.$business['pending_payments'];

        $lines[] = '# HELP htashop_failed_payments_today Payments failed today.';
        $lines[] = '# TYPE htashop_failed_payments_today gauge';
        $lines[] = 'htashop_failed_payments_today '.$business['failed_payments_today'];

        $lines[] = '# HELP htashop_low_stock_skus Inventory rows at or below their low-stock threshold.';
        $lines[] = '# TYPE htashop_low_stock_skus gauge';
        $lines[] = 'htashop_low_stock_skus '.$business['low_stock_skus'];

        $lines[] = '# HELP htashop_out_of_stock_skus Inventory rows with no available stock.';
        $lines[] = '# TYPE htashop_out_of_stock_skus gauge';
        $lines[] = 'htashop_out_of_stock_skus '.$business['out_of_stock_skus'];

        $lines[] = '# HELP htashop_analytics_last_flush_timestamp_seconds Unix time of the last analytics flush.';
        $lines[] = '# TYPE htashop_analytics_last_flush_timestamp_seconds gauge';
        $lines[] = 'htashop_analytics_last_flush_timestamp_seconds '.($business['analytics_last_flush'] ?? 0);

        $lines[] = '# HELP htashop_analytics_pending_counters Counter fields waiting for a flush.';
        $lines[] = '# TYPE htashop_analytics_pending_counters gauge';
        $lines[] = 'htashop_analytics_pending_counters '.$business['analytics_pending'];

        $lines[] = '# HELP htashop_analytics_reconcile_last_difference Purchase events minus confirmed orders (last run).';
        $lines[] = '# TYPE htashop_analytics_reconcile_last_difference gauge';
        $lines[] = 'htashop_analytics_reconcile_last_difference '.$business['reconcile_difference'];

        $lines[] = '# HELP htashop_audit_export_last_success_timestamp_seconds Unix time of the last completed audit export.';
        $lines[] = '# TYPE htashop_audit_export_last_success_timestamp_seconds gauge';
        $lines[] = 'htashop_audit_export_last_success_timestamp_seconds '.($business['audit_export_last_success'] ?? 0);

        $lines = array_merge($lines, $this->httpMetrics());

        return response(implode("\n", $lines)."\n", 200, [
            'Content-Type' => 'text/plain; version=0.0.4; charset=utf-8',
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function businessMetrics(AnalyticsBuffer $buffer): array
    {
        $today = now()->startOfDay();
        $tomorrow = $today->copy()->addDay();
        $yesterday = $today->copy()->subDay();

        $ordersToday = DB::table('orders')
            ->whereNull('deleted_at')
            ->where('created_at', '>=', $today)
            ->where('created_at', '<', $tomorrow)
            ->selectRaw('status, COUNT(*) AS aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(static fn ($count): int => (int) $count)
            ->all();

        $paidToday = DB::table('orders')
            ->whereNull('deleted_at')
            ->whereIn('status', self::PAID_STATUSES)
            ->where('created_at', '>=', $today)
            ->where('created_at', '<', $tomorrow);

        $paidYesterday = DB::table('orders')
            ->whereNull('deleted_at')
            ->whereIn('status', self::PAID_STATUSES)
            ->where('created_at', '>=', $yesterday)
            ->where('created_at', '<', $today);

        $ordersPaidToday = (int) (clone $paidToday)->count();
        $revenueToday = (float) (clone $paidToday)->sum('total_amount');
        $ordersPaidYesterday = (int) (clone $paidYesterday)->count();
        $revenueYesterday = (float) (clone $paidYesterday)->sum('total_amount');

        $inventoryBase = DB::table('inventories')
            ->whereNull('deleted_at')
            ->where('track_inventory', true)
            ->where('is_active', true);

        $lowStock = (int) (clone $inventoryBase)
            ->whereNotNull('low_stock_threshold')
            ->whereRaw('(quantity - reserved) <= low_stock_threshold')
            ->count();

        $outOfStock = (int) (clone $inventoryBase)
            ->whereRaw('(quantity - reserved) <= 0')
            ->count();

        $lastAuditExport = AuditExport::query()
            ->where('status', 'completed')
            ->max('completed_at');

        $reconcile = Cache::get('analytics:reconcile:last');

        return [
            'orders_today_by_status' => $ordersToday,
            'orders_yesterday' => $ordersPaidYesterday,
            'revenue_today' => round($revenueToday, 2),
            'revenue_yesterday' => round($revenueYesterday, 2),
            'aov_today' => $ordersPaidToday > 0 ? round($revenueToday / $ordersPaidToday, 2) : 0.0,
            'pending_payments' => (int) DB::table('payments')->whereNull('deleted_at')->where('status', 'pending')->count(),
            'failed_payments_today' => (int) DB::table('payments')
                ->whereNull('deleted_at')
                ->where('status', 'failed')
                ->where('created_at', '>=', $today)
                ->count(),
            'low_stock_skus' => $lowStock,
            'out_of_stock_skus' => $outOfStock,
            'analytics_last_flush' => $buffer->lastFlushedAt(),
            'analytics_pending' => $buffer->pending(),
            'reconcile_difference' => is_array($reconcile) ? (int) ($reconcile['difference'] ?? 0) : 0,
            'audit_export_last_success' => $lastAuditExport
                ? Carbon::parse((string) $lastAuditExport)->timestamp
                : null,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function httpMetrics(): array
    {
        try {
            $requests = Redis::hgetall('metrics:http:requests') ?: [];
            $durations = Redis::hgetall('metrics:http:durations') ?: [];
            $counts = Redis::hgetall('metrics:http:counts') ?: [];
        } catch (Throwable) {
            return [];
        }

        $max = max(50, (int) config('observability.metrics.max_http_series', 500));
        $lines = [
            '# HELP htashop_http_requests_total API requests by route, method and status.',
            '# TYPE htashop_http_requests_total counter',
        ];

        foreach (array_slice($requests, 0, $max, true) as $field => $value) {
            $parts = explode('|', (string) $field, 3);

            if (count($parts) !== 3) {
                continue;
            }

            [$route, $method, $status] = $parts;
            $lines[] = sprintf(
                'htashop_http_requests_total{route="%s",method="%s",status="%s"} %d',
                $this->label($route),
                $this->label($method),
                $this->label($status),
                (int) $value,
            );
        }

        $lines[] = '# HELP htashop_http_request_duration_seconds_sum Total request duration.';
        $lines[] = '# TYPE htashop_http_request_duration_seconds_sum counter';
        $lines[] = '# HELP htashop_http_request_duration_seconds_count Request count for duration average.';
        $lines[] = '# TYPE htashop_http_request_duration_seconds_count counter';

        foreach (array_slice($durations, 0, $max, true) as $field => $value) {
            $parts = explode('|', (string) $field, 2);

            if (count($parts) !== 2) {
                continue;
            }

            [$route, $method] = $parts;
            $count = (int) ($counts[$field] ?? 0);

            $lines[] = sprintf(
                'htashop_http_request_duration_seconds_sum{route="%s",method="%s"} %.3f',
                $this->label($route),
                $this->label($method),
                ((int) $value) / 1000,
            );
            $lines[] = sprintf(
                'htashop_http_request_duration_seconds_count{route="%s",method="%s"} %d',
                $this->label($route),
                $this->label($method),
                $count,
            );
        }

        return $lines;
    }

    private function label(string $value): string
    {
        return addcslashes($value, "\\\"\n");
    }
}
