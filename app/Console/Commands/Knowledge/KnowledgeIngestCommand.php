<?php

namespace App\Console\Commands\Knowledge;

use App\Services\Knowledge\KnowledgeIngestionService;
use Illuminate\Console\Command;

class KnowledgeIngestCommand extends Command
{
    protected $signature = 'knowledge:ingest
        {--limit= : Maximum source rows to scan per source}
        {--no-prune : Keep derived drafts whose source is no longer public}';

    protected $description = 'Create or refresh draft knowledge entries from published posts and tutorials';

    public function handle(KnowledgeIngestionService $ingestionService): int
    {
        $limit = $this->option('limit') !== null
            ? max(1, (int) $this->option('limit'))
            : null;

        $result = $ingestionService->ingestAll($limit, ! $this->option('no-prune'));

        $this->info("Posts: {$result['posts']} ingested");
        $this->info("Tutorials: {$result['tutorials']} ingested");
        $this->info("Stale drafts removed: {$result['removed']}");
        $this->line('Derived entries are drafts — review and publish them in the admin portal.');

        return self::SUCCESS;
    }
}
