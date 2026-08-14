<?php

namespace App\Jobs\User;

use App\Models\ContactMessage;
use App\Services\Messages\ContactMessageService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendContactReplyEmail implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public $tries = 3;
    public $timeout = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public ContactMessage $message,
        public string $replyMessage,
        public string $replySubject,
        public string $senderName,
        public string $senderEmail,
        public bool $markAsReplied = true
    ) {
        // Set queue connection and queue name
        // $this->onQueue('emails');
        $this->onQueue('default');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info('Sending contact reply email', [
                'message_id' => $this->message->id,
                'recipient' => $this->message->email,
                'subject' => $this->replySubject,
            ]);

            // Send email
            Mail::send('emails.contact-reply', [
                'originalMessage' => $this->message,
                'replyMessage' => $this->replyMessage,
                'senderName' => $this->senderName,
            ], function ($mail) {
                $mail->to($this->message->email, $this->message->name)
                     ->from(config('mail.from.address', 'contact@real3dtech.com'))
                     ->subject($this->replySubject);
            });

            // Mark as replied if requested
            if ($this->markAsReplied) {
                $contactMessageService = new ContactMessageService();
                $contactMessageService->markAsReplied($this->message->id);
            }

            Log::info('Contact reply email sent successfully', [
                'message_id' => $this->message->id,
                'recipient' => $this->message->email,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send contact reply email', [
                'message_id' => $this->message->id,
                'recipient' => $this->message->email,
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
        Log::error('Contact reply email job failed permanently', [
            'message_id' => $this->message->id,
            'recipient' => $this->message->email,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);
    }
}
