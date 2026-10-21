<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Analytics\AnalyticsBuffer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Drain the Redis analytics buffer into MariaDB.
 *
 * Safe to rerun: the whole batch is one transaction and a DB failure restores
 * the counters to the buffer. The lock prevents two schedulers from flushing
 * the same batch.
 */
class FlushAnalyticsCommand extends Command
{
    protected $signature = 'analytics:flush
                            {--dry-run : Report pending counters without flushing}';

    protected $description = 'Flush buffered analytics counters into daily aggregates and product counters';

    public function handle(AnalyticsBuffer $buffer): int
    {
        if ($this->option('dry-run')) {
            $last = $buffer->lastFlushedAt();
            $this->line('Pending counter field(s): '.$buffer->pending());
            $this->line('Last flush: '.($last ? now()->setTimestamp($last)->toIso8601String() : 'never'));

            return self::SUCCESS;
        }

        $lock = Cache::lock('analytics:flush:lock', (int) config('analytics.flush_lock_seconds', 55));

        if (! $lock->get()) {
            $this->warn('Another analytics flush is already running.');

            return self::SUCCESS;
        }

        try {
            $entries = $buffer->pull();

            if ($entries === []) {
                $buffer->markFlushed();
                $this->line('No pending analytics counters.');

                return self::SUCCESS;
            }

            try {
                DB::transaction(function () use ($entries): void {
                    foreach ($entries as $entry) {
                        $this->applyEntry($entry);
                    }
                });
            } catch (Throwable $exception) {
                $buffer->restore($entries);
                report($exception);
                $this->error('Analytics flush failed; counters restored: '.$exception->getMessage());

                return self::FAILURE;
            }

            $buffer->markFlushed();

            $daily = count(array_filter($entries, static fn (array $entry): bool => $entry['period'] !== 'lifetime'));
            $lifetime = count($entries) - $daily;

            $this->info("Flushed {$daily} daily aggregate(s) and {$lifetime} lifetime counter(s).");

            return self::SUCCESS;
        } finally {
            $lock->release();
        }
    }

    private function applyEntry(array $entry): void
    {
        if ($entry['period'] === 'lifetime') {
            $this->applyLifetime($entry);

            return;
        }

        $this->upsertDaily($entry);
    }

    private function applyLifetime(array $entry): void
    {
        if (($entry['entity_type'] ?? null) !== 'product') {
            return;
        }

        $column = config('analytics.counters')[$entry['metric']] ?? null;

        if (! is_string($column) || ! preg_match('/^[a-z_]+$/', $column)) {
            return;
        }

        $productId = (int) $entry['entity_id'];

        if ($productId <= 0) {
            return;
        }

        DB::table('products')->where('id', $productId)->increment($column, (int) $entry['count']);
    }

    private function upsertDaily(array $entry): void
    {
        $period = (string) $entry['period'];

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $period) !== 1) {
            return;
        }

        $count = (int) ($entry['count'] ?? 0);
        $value = (float) ($entry['value'] ?? 0.0);

        if ($count === 0 && $value === 0.0) {
            return;
        }

        $key = [
            'tenant_id' => (int) ($entry['tenant_id'] ?? 0),
            'metric_date' => $period,
            'entity_type' => (string) $entry['entity_type'],
            'entity_id' => (string) $entry['entity_id'],
            'metric_name' => (string) $entry['metric'],
            'source' => (string) $entry['source'],
        ];

        $row = DB::table('analytics_daily')->where($key)->lockForUpdate()->first();

        $meta = is_array($entry['meta'] ?? null) ? $entry['meta'] : [];
        $label = $entry['label'] ?? null;

        if ($row) {
            $existingMeta = json_decode((string) ($row->metadata ?? '[]'), true);
            $existingMeta = is_array($existingMeta) ? $existingMeta : [];
            $mergedMeta = array_merge($existingMeta, $meta);

            DB::table('analytics_daily')->where('id', $row->id)->update([
                'count' => (int) $row->count + $count,
                'value_sum' => round((float) $row->value_sum + $value, 4),
                'label' => $label ?? $row->label,
                'metadata' => $mergedMeta !== []
                    ? json_encode($mergedMeta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                    : $row->metadata,
                'updated_at' => now(),
            ]);

            return;
        }

        DB::table('analytics_daily')->insert($key + [
            'count' => $count,
            'value_sum' => $value,
            'label' => $label,
            'metadata' => $meta !== []
                ? json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
