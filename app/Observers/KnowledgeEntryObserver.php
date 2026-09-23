<?php

declare(strict_types=1);

namespace App\Observers;

use App\Jobs\Knowledge\IndexKnowledgeEntryJob;
use App\Models\KnowledgeEntry;

/**
 * Queues a knowledge entry for (re)indexing whenever it is written or removed.
 */
class KnowledgeEntryObserver
{
    public function saved(KnowledgeEntry $entry): void
    {
        if (! $this->indexEnabled()) {
            return;
        }

        dispatch(new IndexKnowledgeEntryJob((int) $entry->id, 'upsert'));
    }

    public function deleted(KnowledgeEntry $entry): void
    {
        if (! $this->indexEnabled()) {
            return;
        }

        dispatch(new IndexKnowledgeEntryJob((int) $entry->id, 'delete'));
    }

    protected function indexEnabled(): bool
    {
        return (bool) config('typesense.enabled', false) && config('typesense.api_key') !== '';
    }
}
