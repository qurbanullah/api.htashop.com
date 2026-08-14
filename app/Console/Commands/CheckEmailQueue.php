<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckEmailQueue extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'queue:check-emails {--show-jobs : Show individual jobs}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check the status of email jobs in the queue';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Email Queue Status');
        $this->info('==================');

        // Get pending jobs
        $pendingJobs = DB::table('jobs')
            ->where('queue', 'emails')
            ->orderBy('created_at', 'desc')
            ->get();

        // Get failed jobs
        $failedJobs = DB::table('failed_jobs')
            ->where('payload', 'like', '%SendContactReplyEmail%')
            ->orderBy('failed_at', 'desc')
            ->get();

        $this->info("Pending Email Jobs: " . $pendingJobs->count());
        $this->info("Failed Email Jobs: " . $failedJobs->count());

        if ($this->option('show-jobs')) {
            if ($pendingJobs->count() > 0) {
                $this->info("\nPending Jobs:");
                $this->table(
                    ['ID', 'Queue', 'Attempts', 'Created'],
                    $pendingJobs->map(function ($job) {
                        return [
                            $job->id,
                            $job->queue,
                            $job->attempts,
                            $job->created_at,
                        ];
                    })->toArray()
                );
            }

            if ($failedJobs->count() > 0) {
                $this->error("\nFailed Jobs:");
                $this->table(
                    ['ID', 'Exception', 'Failed At'],
                    $failedJobs->map(function ($job) {
                        $exception = json_decode($job->exception, true);
                        return [
                            $job->id,
                            substr($exception['message'] ?? 'Unknown error', 0, 50) . '...',
                            $job->failed_at,
                        ];
                    })->toArray()
                );
            }
        }

        // Check if queue worker is running
        $this->info("\nQueue Worker Status:");
        exec('pgrep -f "queue:work"', $output, $return_var);

        if ($return_var === 0) {
            $this->info("✅ Queue worker is running (PIDs: " . implode(', ', $output) . ")");
        } else {
            $this->warn("⚠️  No queue worker detected. Start with: php artisan queue:work");
        }

        $this->info("\nCommands:");
        $this->info("  Start worker: php artisan queue:work --queue=emails");
        $this->info("  Retry failed: php artisan queue:retry all");
        $this->info("  Clear failed: php artisan queue:flush");

        return 0;
    }
}
