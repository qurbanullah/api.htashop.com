<?php

namespace App\Jobs\Import;

use App\Enums\ProductImportStatus;
use App\Models\ProductImport;
use App\Services\Import\ProductImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Applies one bounded chunk of a staged import, then re-queues itself for the
 * next one.
 *
 * Chunking this way keeps every job well inside the worker timeout and
 * `retry_after`, so a 50,000-row file is applied as a sequence of small,
 * observable steps instead of one all-or-nothing run. Progress is persisted
 * after each chunk, so a crashed worker resumes from the last row it finished.
 */
class ApplyProductImportChunkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Queue the chunks run on.
     *
     * `default` unless an operator opts into a dedicated queue, because the
     * worker's `--queue` list is deployment configuration: a queue no worker
     * subscribes to leaves the import sitting at "importing" forever. See
     * config/queue.php.
     */
    public static function queueName(): string
    {
        return (string) config('queue.imports_queue', 'default');
    }

    /** Must stay below `queue.connections.*.retry_after` (90s). */
    public int $timeout = 60;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60, 180];

    public function __construct(
        public int $importId,
        public int $afterRowId = 0,
    ) {}

    public function handle(ProductImportService $service): void
    {
        $import = ProductImport::query()->find($this->importId);

        if ($import === null || $import->status !== ProductImportStatus::IMPORTING) {
            // Cancelled, deleted or already settled — nothing left to do.
            return;
        }

        $chunk = $service->applyChunk($import, $this->afterRowId);

        if ($chunk['has_more']) {
            self::dispatch($import->id, $chunk['next_row_id'])->onQueue(self::queueName());

            return;
        }

        $service->finishApply($import->refresh());
    }

    /**
     * Leave the import in a terminal state the operator can see and retry,
     * instead of a record that looks like it is still running.
     */
    public function failed(?Throwable $exception): void
    {
        $import = ProductImport::query()->find($this->importId);

        if ($import === null || $import->status !== ProductImportStatus::IMPORTING) {
            return;
        }

        $import->update([
            'status' => ProductImportStatus::FAILED,
            'error_message' => $exception?->getMessage() ?? 'The import worker stopped unexpectedly.',
            'finished_at' => now(),
        ]);
    }
}
