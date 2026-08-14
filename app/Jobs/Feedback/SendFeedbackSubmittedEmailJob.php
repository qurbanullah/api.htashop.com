<?php

namespace App\Jobs\Feedback;

use App\Mail\FeedbackSubmittedMail;
use App\Models\Feedback;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendFeedbackSubmittedEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Feedback $feedback
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Mail::to($this->feedback->email)->send(
                new FeedbackSubmittedMail($this->feedback)
            );

            Log::info('Feedback confirmation email sent successfully', [
                'feedback_id' => $this->feedback->id,
                'email' => $this->feedback->email,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send feedback confirmation email', [
                'feedback_id' => $this->feedback->id,
                'email' => $this->feedback->email,
                'error' => $e->getMessage(),
            ]);

            // Optionally rethrow to trigger job retry
            // throw $e;
        }
    }
}
