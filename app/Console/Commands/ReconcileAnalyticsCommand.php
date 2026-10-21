<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Compare the server-side purchase events with the orders table for one day.
 *
 * A mismatch means an order committed without its analytics event (or a
 * duplicate event slipped past the order-level dedupe). It is a data-quality
 * signal, not a reason to block checkout.
 */
class ReconcileAnalyticsCommand extends Command
{
    protected $signature = 'analytics:reconcile
                            {--date= : UTC date (YYYY-MM-DD); default yesterday}
                            {--tolerance=0 : Allowed absolute difference before warning}';

    protected $description = 'Reconcile purchase analytics against confirmed orders for a day';

    public function handle(): int
    {
        $dateOption = trim((string) $this->option('date'));

        try {
            $date = $dateOption !== ''
                ? Carbon::createFromFormat('Y-m-d', $dateOption, 'UTC')
                : Carbon::now('UTC')->subDay();
        } catch (\Throwable) {
            $this->error('--date must be a valid YYYY-MM-DD date.');

            return self::FAILURE;
        }

        if (! $date instanceof Carbon || ($dateOption !== '' && $date->format('Y-m-d') !== $dateOption)) {
            $this->error('--date must be a valid YYYY-MM-DD date.');

            return self::FAILURE;
        }

        $start = $date->copy()->startOfDay();
        $end = $start->copy()->addDay();
        $tolerance = max(0, (int) $this->option('tolerance'));

        $orderCount = Order::query()
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $end)
            ->whereIn('status', [
                OrderStatus::CONFIRMED,
                OrderStatus::PROCESSING,
                OrderStatus::AWAITING_SOURCING,
                OrderStatus::IN_TRANSIT,
                OrderStatus::IN_CUSTOMS,
                OrderStatus::ARRIVED_WAREHOUSE,
                OrderStatus::SHIPPED,
                OrderStatus::DELIVERED,
            ])
            ->count();

        $eventCount = (int) DB::table('analytics_daily')
            ->where('metric_date', $start->toDateString())
            ->where('entity_type', 'store')
            ->where('metric_name', 'purchase')
            ->where('source', 'server')
            ->sum('count');

        $difference = $orderCount - $eventCount;

        Cache::forever('analytics:reconcile:last', [
            'date' => $start->toDateString(),
            'orders' => $orderCount,
            'events' => $eventCount,
            'difference' => $difference,
            'checked_at' => now()->toIso8601String(),
        ]);

        $this->line("{$start->toDateString()}: {$orderCount} order(s), {$eventCount} purchase event(s), difference {$difference}");

        if (abs($difference) > $tolerance) {
            $this->warn('Purchase analytics and the orders table disagree. Check the analytics worker and the order observer.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
