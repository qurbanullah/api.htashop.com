<?php

namespace App\Jobs\Feedback;

use App\Mail\FeedbackCommentMail;
use App\Models\Comment;
use App\Models\Feedback;
use App\Services\Email\EmailLogService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

/**
 * Job to send email notification when a comment is added to feedback
 */
class SendFeedbackCommentEmailJob implements ShouldQueue
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
        public Comment $comment
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $emailLogService = new EmailLogService();

            // Prepare the mailable
            $mailable = new FeedbackCommentMail($this->feedback, $this->comment);

            // Create email log
            $emailLog = $emailLogService->createLog(
                $this->feedback->email,
                $this->feedback->name ?? 'User',
                $mailable,
                'feedback',
                $this->feedback->id,
                [
                    'action' => 'comment_added',
                    'comment_id' => $this->comment->id,
                    'commenter_name' => $this->comment->user->name ?? 'Admin',
                    'is_internal' => $this->comment->is_internal,
                ]
            );

            try {
                // Send email to feedback submitter
                Mail::to($this->feedback->email)->send($mailable);

                // Mark email as sent
                $emailLogService->markAsSent($emailLog);

                Log::info('Feedback comment email sent successfully', [
                    'feedback_id' => $this->feedback->id,
                    'comment_id' => $this->comment->id,
                    'recipient' => $this->feedback->email,
                    'email_log_id' => $emailLog->id,
                ]);

            } catch (\Exception $e) {
                // Mark email as failed
                $emailLogService->markAsFailed($emailLog, $e);

                Log::error('Failed to send feedback comment email', [
                    'feedback_id' => $this->feedback->id,
                    'comment_id' => $this->comment->id,
                    'recipient' => $this->feedback->email,
                    'error' => $e->getMessage(),
                    'email_log_id' => $emailLog->id,
                ]);

                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('Feedback comment email job failed', [
                'feedback_id' => $this->feedback->id,
                'comment_id' => $this->comment->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Feedback comment email job permanently failed', [
            'feedback_id' => $this->feedback->id,
            'comment_id' => $this->comment->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
