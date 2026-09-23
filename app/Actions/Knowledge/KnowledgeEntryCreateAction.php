<?php

declare(strict_types=1);

namespace App\Actions\Knowledge;

use App\Models\KnowledgeEntry;
use Illuminate\Support\Facades\DB;

/**
 * Creates a knowledge entry.
 */
class KnowledgeEntryCreateAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): KnowledgeEntry
    {
        return DB::transaction(fn (): KnowledgeEntry => KnowledgeEntry::create($data));
    }
}
