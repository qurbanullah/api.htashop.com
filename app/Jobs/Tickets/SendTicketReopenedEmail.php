<?php

declare(strict_types=1);

namespace App\Jobs\Tickets;

use App\Helpers\AdminHelper;
use App\Mail\Tickets\TicketReopened;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendTicketReopenedEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
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
            // Load relationships
            $this->ticket->load(['reporter', 'activeAssignments.assignedTo']);

            // Send email to assigned user if exists
            $assignment = $this->ticket->activeAssignments->first();
            if ($assignment && $assignment->assignedTo && $assignment->assignedTo->email) {
                Mail::to($assignment->assignedTo->email)
                    ->send(new TicketReopened($this->ticket, 'assignee'));
            }

            // Send alert to support managers
            // Using AdminHelper to get cached managers and avoid duplicates
            $supportManagers = AdminHelper::getManagers();

            foreach ($supportManagers as $manager) {
                if ($manager->email) {
                    Mail::to($manager->email)
                        ->send(new TicketReopened($this->ticket, 'support'));
                }
            }

            // Notify ticket creator (if reopened by admin)
            if ($this->ticket->submitter_email) {
                Mail::to($this->ticket->submitter_email)
                    ->send(new TicketReopened($this->ticket, 'creator'));
            }
        } catch (\Exception $e) {
            Log::error('Failed to send ticket reopened emails', [
                'ticket_id' => $this->ticket->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('SendTicketReopenedEmail job failed after all retries', [
            'ticket_id' => $this->ticket->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
