<?php

namespace App\Jobs\Newsletter;

use App\Services\Newsletter\NewsletterService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CreateNewsletterJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected array $newsletterData;
    protected int $createdByUserId;

    /**
     * Create a new job instance.
     */
    public function __construct(array $newsletterData, int $createdByUserId)
    {
        $this->newsletterData = $newsletterData;
        $this->createdByUserId = $createdByUserId;
    }

    /**
     * Execute the job.
     */
    public function handle(NewsletterService $newsletterService): void
    {
        // Pass the user ID explicitly to the service since auth() is not available in queue context
        $newsletterService->createNewsletter($this->newsletterData, $this->createdByUserId);
    }
}
