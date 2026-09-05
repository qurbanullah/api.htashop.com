<?php

namespace App\Console\Commands;

use App\Services\Search\ProductSearchService;
use Illuminate\Console\Command;

class SearchReindexCommand extends Command
{
    protected $signature = 'search:reindex {--chunk=500 : Products per import batch} {--fresh : Drop and recreate the collection first}';

    protected $description = 'Create the Typesense products collection and reindex all products';

    public function handle(ProductSearchService $searchService): int
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

        $this->info("Indexed {$count} products.");

        return self::SUCCESS;
    }
}
