<?php

namespace App\Console\Commands;

use App\Actions\Quotes\EnsureQuoteDocumentsAction;
use Illuminate\Console\Command;

class EnsureQuoteDocumentsCommand extends Command
{
    protected $signature = 'quotes:ensure-documents
        {--days=30 : Only check revisions sent within this many days}
        {--limit=200 : Maximum revisions to inspect per run}
        {--dry-run : Report what would be regenerated without writing}';

    protected $description = 'Backfill missing quotation PDFs for recently sent revisions';

    public function handle(EnsureQuoteDocumentsAction $action): int
    {
        $days = (int) $this->option('days');
        $limit = (int) $this->option('limit');
        $dryRun = (bool) $this->option('dry-run');

        $stats = $action->execute($days, $limit, $dryRun);

        $this->info(sprintf(
            'Checked %d revision(s): generated %d, skipped %d, failed %d%s.',
            $stats['checked'],
            $stats['generated'],
            $stats['skipped'],
            $stats['failed'],
            $dryRun ? ", {$stats['pending']} pending (dry run)" : '',
        ));

        return $stats['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
