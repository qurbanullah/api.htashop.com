<?php

declare(strict_types=1);

namespace App\Jobs\Tickets;

use App\Helpers\AdminHelper;
use App\Mail\Tickets\TicketCreated;
use App\Mail\Tickets\TicketCreatedAdmin;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendTicketCreatedEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $backoff = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Ticket $ticket
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Send confirmation email to ticket creator
            if ($this->ticket->submitter_email) {
                Mail::to($this->ticket->submitter_email)
                    ->send(new TicketCreated($this->ticket));
            }

            // Send notification to all support staff (support-assistant and above)
            // Using AdminHelper to get cached support staff and avoid duplicates
            $supportUsers = AdminHelper::getSupportStaffExcludingUser($this->ticket->user_id);

            foreach ($supportUsers as $user) {
                if ($user->email) {
                    Mail::to($user->email)
                        ->send(new TicketCreatedAdmin($this->ticket));
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to send ticket created emails', [
                'ticket_id' => $this->ticket->id,
                'error' => $e->getMessage(),
            ]);
            throw $e; // Rethrow to trigger retry
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('SendTicketCreatedEmail job failed after all retries', [
            'ticket_id' => $this->ticket->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
