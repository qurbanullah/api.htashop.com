<?php

namespace App\Jobs\Feedback;

use App\Models\Feedback;
use App\Services\Feedbacks\FeedbackService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class SendFeedbackReplyEmail implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public $tries = 3;
    public $timeout = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Feedback $feedback,
        public string $replyMessage,
        public string $replySubject,
        public string $senderName,
        public string $senderEmail,
        public bool $markAsReplied = true,
        public ?int $repliedById = null
    ) {
        // Set queue connection and queue name
        $this->onQueue('default');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info('Sending feedback reply email', [
                'feedback_id' => $this->feedback->id,
                'feedback_type' => $this->feedback->type,
                'recipient' => $this->feedback->email,
                'subject' => $this->replySubject,
            ]);

            // Send email
            Mail::send('emails.feedback-reply', [
                'originalFeedback' => $this->feedback,
                'replyMessage' => $this->replyMessage,
                'senderName' => $this->senderName,
            ], function ($mail) {
                $mail->to($this->feedback->email, $this->feedback->name)
                     ->from($this->senderEmail, $this->senderName)
                     ->subject($this->replySubject);
            });

            // Mark as replied if requested
            if ($this->markAsReplied) {
                $feedbackService = new FeedbackService();
                $feedbackService->markAsReplied(
                    $this->feedback->id,
                    $this->replyMessage,
                    $this->repliedById
                );
            }

            Log::info('Feedback reply email sent successfully', [
                'feedback_id' => $this->feedback->id,
                'feedback_type' => $this->feedback->type,
                'recipient' => $this->feedback->email,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send feedback reply email', [
                'feedback_id' => $this->feedback->id,
                'recipient' => $this->feedback->email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-throw the exception to let Laravel handle retries
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Feedback reply email job failed permanently', [
            'feedback_id' => $this->feedback->id,
            'recipient' => $this->feedback->email,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);
    }
}
