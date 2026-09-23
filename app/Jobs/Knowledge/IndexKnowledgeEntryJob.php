<?php

declare(strict_types=1);

namespace App\Jobs\Knowledge;

use App\Models\KnowledgeEntry;
use App\Services\Knowledge\KnowledgeSearchService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Keeps the Typesense knowledge index in sync with `knowledge_entries`.
 * Dispatched on the queue so knowledge writes are never blocked by searching.
 */
class IndexKnowledgeEntryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public int $entryId,
        public string $action = 'upsert', // 'upsert' | 'delete'
    ) {}

    public function handle(KnowledgeSearchService $searchService): void
    {
        if (! $searchService->isEnabled()) {
            return;
        }

        if ($this->action === 'delete') {
            $searchService->deleteEntry($this->entryId);

            return;
        }

        $entry = KnowledgeEntry::find($this->entryId);

        if ($entry) {
            $searchService->indexEntry($entry);

            return;
        }

        // Entry removed between dispatch and execution.
        $searchService->deleteEntry($this->entryId);
    }
}
