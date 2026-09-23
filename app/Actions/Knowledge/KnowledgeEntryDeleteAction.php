<?php

declare(strict_types=1);

namespace App\Actions\Knowledge;

use App\Models\KnowledgeEntry;
use Illuminate\Support\Facades\DB;

/**
 * Deletes a knowledge entry.
 */
class KnowledgeEntryDeleteAction
{
    public function handle(KnowledgeEntry $entry): bool
    {
        return DB::transaction(fn (): bool => (bool) $entry->delete());
    }
}
