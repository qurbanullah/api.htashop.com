<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Newsletter\NewsletterService;

class SendScheduledNewsletters extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'newsletters:send-scheduled';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send all newsletters that are scheduled to be sent';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $newsletterService = app(NewsletterService::class);
        $scheduledNewsletters = $newsletterService->getScheduledNewsletters();

        if ($scheduledNewsletters->isEmpty()) {
            $this->info('No newsletters scheduled for sending.');
            return;
        }

        $this->info("Found {$scheduledNewsletters->count()} newsletter(s) ready to send.");

        foreach ($scheduledNewsletters as $newsletter) {
            try {
                $this->info("Sending newsletter: {$newsletter->title}");

                $result = $newsletterService->sendNewsletter($newsletter);

                $this->info("✓ Newsletter sent successfully!");
                $this->line("  - Sent to: {$result['sent_count']} recipients");
                $this->line("  - Failed: {$result['failed_count']} recipients");

            } catch (\Exception $e) {
                $this->error("✗ Failed to send newsletter '{$newsletter->title}': {$e->getMessage()}");
            }
        }

        $this->info('Scheduled newsletter sending completed.');
    }
}
