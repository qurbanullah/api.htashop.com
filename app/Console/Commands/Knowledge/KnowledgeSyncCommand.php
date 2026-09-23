<?php

namespace App\Console\Commands\Knowledge;

use App\Services\Knowledge\KnowledgeSearchService;
use Illuminate\Console\Command;

class KnowledgeSyncCommand extends Command
{
    protected $signature = 'knowledge:sync {--chunk=200 : Entries per batch} {--fresh : Drop and recreate the collection first}';

    protected $description = 'Create the Typesense knowledge collection and reindex all published entries';

    public function handle(KnowledgeSearchService $searchService): int
    {
        if (! $searchService->isEnabled()) {
            $this->error('Typesense is not enabled. Set TYPESENSE_ENABLED=true and TYPESENSE_API_KEY in the environment.');

            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            $searchService->dropCollection();
            $this->info('Collection dropped.');
        }

        $searchService->ensureCollection();
        $this->info('Collection ready.');

        $count = $searchService->reindexAll((int) $this->option('chunk'));

        $this->info("Indexed {$count} knowledge entries.");

        return self::SUCCESS;
    }
}
