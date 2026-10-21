<?php

declare(strict_types=1);

namespace App\Console\Commands\Audit;

use App\Models\Audit;
use App\Models\AuditExport;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Remove audit rows past the retention window.
 *
 * A day is only eligible when a completed export exists for it. This is the
 * safety interlock that prevents `audit:prune` from silently deleting evidence
 * when the exporter is broken.
 */
class PruneAuditLogsCommand extends Command
{
    protected $signature = 'audit:prune
                            {--days= : Override the configured retention window}
                            {--dry-run : Report what would be deleted without deleting}';

    protected $description = 'Prune exported audit rows past their retention window';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?: config('observability.audit.retention_days', 365));
        $days = max(1, $days);
        $dryRun = (bool) $this->option('dry-run');

        $oldest = Audit::query()->min('occurred_at');

        if ($oldest === null) {
            $this->info('No audit rows to prune.');

            return self::SUCCESS;
        }

        $cutoff = Carbon::now('UTC')->subDays($days)->startOfDay();
        $date = Carbon::parse((string) $oldest, 'UTC')->startOfDay();
        $deleted = 0;
        $skipped = 0;
        $guard = 0;

        while ($date->lt($cutoff) && $guard++ < 4000) {
            $dateString = $date->toDateString();
            $exported = AuditExport::query()
                ->where('export_date', $date->copy()->startOfDay())
                ->where('status', 'completed')
                ->exists();

            if (! $exported) {
                $skipped++;
                $date = $date->addDay();

                continue;
            }

            $query = Audit::query()
                ->where('occurred_at', '>=', $date->copy()->startOfDay())
                ->where('occurred_at', '<', $date->copy()->addDay()->startOfDay());

            if ($dryRun) {
                $deleted += (clone $query)->count();
            } else {
                $deleted += (clone $query)->delete();
            }

            $date = $date->addDay();
        }

        $mode = $dryRun ? 'Would delete' : 'Deleted';
        $this->info("{$mode} {$deleted} audit row(s); {$skipped} unexported day(s) skipped.");

        if ($skipped > 0) {
            $this->warn('Unexported days were kept. Run audit:export before pruning.');
        }

        return self::SUCCESS;
    }
}
