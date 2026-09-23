<?php

declare(strict_types=1);

namespace App\Actions\Knowledge;

use App\Models\KnowledgeEntry;
use Illuminate\Support\Facades\DB;

/**
 * Updates a knowledge entry.
 */
class KnowledgeEntryUpdateAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(KnowledgeEntry $entry, array $data): KnowledgeEntry
    {
        return DB::transaction(function () use ($entry, $data): KnowledgeEntry {
            $entry->fill($data)->save();

            return $entry->refresh();
        });
    }
}
