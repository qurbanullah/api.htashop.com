<?php

namespace App\Console\Commands;

use App\Actions\Quotes\ExpireQuoteResponsesAction;
use Illuminate\Console\Command;

class ExpireQuotesCommand extends Command
{
    protected $signature = 'quotes:expire
        {--dry-run : Report what would change without writing or emailing}';

    protected $description = 'Remind buyers whose quote is about to expire and close quotes past their validity';

    public function handle(ExpireQuoteResponsesAction $action): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $result = $action->execute($dryRun);

        $this->info(sprintf(
            '%s %d reminder(s), expired %d revision(s), closed %d quote request(s).',
            $dryRun ? 'Would send' : 'Sent',
            $result['reminders'],
            $result['expired'],
            $result['requests_expired'],
        ));

        return self::SUCCESS;
    }
}
