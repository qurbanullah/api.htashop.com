<?php

namespace App\Jobs\Feedback;

use App\Mail\FeedbackReplyMail;
use App\Models\Feedback;
use App\Services\Email\EmailLogService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendFeedbackReplyEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The maximum number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Feedback $feedback,
        public string $replyMessage,
        public string $replySubject,
        public string $senderName,
        public string $senderEmail
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $emailLogService = new EmailLogService();

            // Prepare the mailable
            $mailable = new FeedbackReplyMail(
                $this->feedback,
                $this->replyMessage,
                $this->replySubject,
                $this->senderName,
                $this->senderEmail
            );

            // Create email log
            $emailLog = $emailLogService->createLog(
                $this->feedback->email,
                $this->feedback->name ?? 'User',
                $mailable,
                'feedback',
                $this->feedback->id,
                [
                    'action' => 'reply',
                    'reply_subject' => $this->replySubject,
                    'reply_from' => $this->senderName,
                ]
            );

            try {
                // Send email using default MAIL_USERNAME from .env
                // The replyTo is set to the admin's email in the mailable
                Mail::to($this->feedback->email)->send($mailable);

                // Mark email as sent
                $emailLogService->markAsSent($emailLog);

                Log::info('Feedback reply email sent successfully', [
                    'feedback_id' => $this->feedback->id,
                    'email' => $this->feedback->email,
                    'reply_from' => $this->senderName,
                    'email_log_id' => $emailLog->id,
                ]);
            } catch (\Exception $e) {
                // Mark email as failed
                $emailLogService->markAsFailed($emailLog, $e);
                throw $e;
            }
        } catch (\Exception $e) {
            Log::error('Failed to send feedback reply email', [
                'feedback_id' => $this->feedback->id,
                'email' => $this->feedback->email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-throw the exception to trigger job retry
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Feedback reply email job failed after all attempts', [
            'feedback_id' => $this->feedback->id,
            'email' => $this->feedback->email,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
