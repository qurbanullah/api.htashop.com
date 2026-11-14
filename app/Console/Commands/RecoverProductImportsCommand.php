<?php

namespace App\Console\Commands;

use App\Enums\ProductImportRowStatus;
use App\Enums\ProductImportStatus;
use App\Jobs\Import\ApplyProductImportChunkJob;
use App\Models\ProductImport;
use App\Services\Import\ProductImportService;
use Illuminate\Console\Command;

/**
 * Safety net for imports whose worker stopped before finishing.
 *
 * A queued job is not durable state: if a worker dies, is rolled mid-job, or is
 * not subscribed to the queue the job was pushed to, the record stays at
 * "importing" with rows still staged and the operator sees a progress bar that
 * never moves. This command makes that visible and recoverable — it resumes
 * from the last applied row, or settles a record whose last chunk wrote
 * everything and then died before updating the status.
 */
class RecoverProductImportsCommand extends Command
{
    protected $signature = 'products:imports:recover
        {--stale-minutes= : Recovery window in minutes (default: config/queue.php)}
        {--dry-run : Report stalled imports without queueing anything}';

    protected $description = 'Resume product imports abandoned by a stopped queue worker';

    public function handle(ProductImportService $service): int
    {
        $minutes = $this->option('stale-minutes');
        $minutes = max(1, (int) ($minutes !== null ? $minutes : config('queue.imports_stale_minutes', 10)));
        $dryRun = (bool) $this->option('dry-run');

        $stalled = ProductImport::query()
            ->where('status', ProductImportStatus::IMPORTING)
            ->where('updated_at', '<=', now()->subMinutes($minutes))
            ->orderBy('id')
            ->get();

        if ($stalled->isEmpty()) {
            $this->info('No stalled product imports.');

            return self::SUCCESS;
        }

        foreach ($stalled as $import) {
            $remaining = $import->rows()
                ->where('status', ProductImportRowStatus::VALID)
                ->count();

            if ($remaining === 0) {
                if (! $dryRun) {
                    $service->finishApply($import);
                }

                $this->line("  {$import->uuid}: every row was applied; settled as finished.");

                continue;
            }

            $cursor = $service->resumeCursor($import);

            if ($dryRun) {
                $this->line("  {$import->uuid}: would resume {$remaining} rows after #{$cursor}.");

                continue;
            }

            ApplyProductImportChunkJob::dispatch($import->id, $cursor)
                ->onQueue(ApplyProductImportChunkJob::queueName());

            // Restart the clock so the next run waits a full window before
            // queueing the same import again.
            $import->touch();

            $this->line("  {$import->uuid}: resumed {$remaining} rows after #{$cursor}.");
        }

        return self::SUCCESS;
    }
}
