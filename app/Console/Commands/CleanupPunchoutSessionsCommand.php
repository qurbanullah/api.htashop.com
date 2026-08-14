<?php

namespace App\Console\Commands;

use App\Actions\Punchout\CleanupPunchoutSessionsAction;
use Illuminate\Console\Command;

class CleanupPunchoutSessionsCommand extends Command
{
    protected $signature = 'punchout:sessions:cleanup
                            {--retention-days= : Days to keep completed or expired punchout sessions before deletion}
                            {--dry-run : Show counts without mutating punchout sessions}';

    protected $description = 'Expire open punchout sessions that have timed out and prune old terminal sessions';

    public function handle(CleanupPunchoutSessionsAction $cleanupPunchoutSessionsAction): int
    {
        $retentionDays = (int) ($this->option('retention-days') ?: config('punchout.session_cleanup_retention_days', 7));
        $dryRun = (bool) $this->option('dry-run');

        $results = $cleanupPunchoutSessionsAction->handle($retentionDays, $dryRun);

        $this->info('Punchout session cleanup completed.');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Expired open sessions', $results['expired_count']],
                ['Pruned terminal sessions', $results['pruned_count']],
                ['Dry run', $results['dry_run'] ? 'Yes' : 'No'],
                ['Retention days', $results['retention_days']],
            ]
        );

        return Command::SUCCESS;
    }
}
