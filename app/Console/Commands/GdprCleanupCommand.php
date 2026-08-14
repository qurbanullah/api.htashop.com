<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Gdpr\GdprService;

class GdprCleanupCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gdpr:cleanup
                            {--retention-days=30 : Number of days after soft deletion before permanent deletion}
                            {--dry-run : Show what would be deleted without actually deleting}
                            {--no-anonymize : Do not anonymize user data (keep original names/emails)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Perform GDPR compliant cleanup by permanently deleting users past retention period';

    /**
     * Execute the console command.
     */
    public function handle(GdprService $gdprService)
    {
        $retentionDays = (int) $this->option('retention-days');
        $dryRun = $this->option('dry-run');
        $anonymize = !$this->option('no-anonymize');

        $this->info("GDPR Cleanup Process Starting...");
        $this->info("Retention Period: {$retentionDays} days");
        $this->info("Anonymize Data: " . ($anonymize ? 'Yes' : 'No'));
        $this->info("Dry Run: " . ($dryRun ? 'Yes' : 'No'));
        $this->newLine();

        // Get eligible users
        $eligibleUsers = $gdprService->getUsersEligibleForPermanentDeletion($retentionDays);

        if ($eligibleUsers->isEmpty()) {
            $this->info("✅ No users found eligible for permanent deletion.");
            return Command::SUCCESS;
        }

        $this->table(
            ['User ID', 'Email', 'Deleted At', 'Days Since Deletion'],
            $eligibleUsers->map(function ($user) {
                return [
                    $user->id,
                    $user->email,
                    $user->deleted_at->format('Y-m-d H:i:s'),
                    $user->deleted_at->diffInDays(now())
                ];
            })
        );

        $this->warn("Found {$eligibleUsers->count()} users eligible for permanent deletion.");

        if ($dryRun) {
            $this->info("🔍 DRY RUN: These users would be permanently deleted.");
            return Command::SUCCESS;
        }

        if (!$this->confirm('Are you sure you want to permanently delete these users? This action cannot be undone.')) {
            $this->info("Operation cancelled.");
            return Command::SUCCESS;
        }

        // Perform the cleanup
        $progressBar = $this->output->createProgressBar($eligibleUsers->count());
        $progressBar->start();

        $results = $gdprService->performAutomatedGdprCleanup(
            $retentionDays,
            [
                'type' => 'manual_gdpr_cleanup_command',
                'executed_by' => 'artisan_command',
                'executed_at' => now()->toISOString()
            ],
            $anonymize
        );

        $progressBar->finish();
        $this->newLine(2);

        // Display results
        $this->info("✅ GDPR Cleanup Completed!");
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Eligible', $results['total_eligible']],
                ['Successfully Deleted', $results['successfully_deleted']],
                ['Failed Deletions', count($results['failed_deletions'])],
            ]
        );

        if (!empty($results['failed_deletions'])) {
            $this->error("❌ Failed Deletions:");
            $this->table(
                ['User ID', 'Error'],
                collect($results['failed_deletions'])->map(function ($failure) {
                    return [$failure['user_id'], $failure['error']];
                })
            );
        }

        return Command::SUCCESS;
    }
}
