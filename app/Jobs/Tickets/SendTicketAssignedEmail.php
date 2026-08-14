<?php

declare(strict_types=1);

namespace App\Jobs\Tickets;

use App\Mail\Tickets\TicketAssigned;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendTicketAssignedEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Ticket $ticket,
        public User $assignee,
        public ?User $previousAssignee = null
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Send email to new assignee
            if ($this->assignee->email) {
                Mail::to($this->assignee->email)
                    ->send(new TicketAssigned($this->ticket, $this->assignee, 'assignee'));
            }

            // Send email to ticket creator
            if ($this->ticket->submitter_email) {
                Mail::to($this->ticket->submitter_email)
                    ->send(new TicketAssigned($this->ticket, $this->assignee, 'creator'));
            }

            // If reassigning, notify previous assignee
            if ($this->previousAssignee && $this->previousAssignee->email && $this->previousAssignee->id !== $this->assignee->id) {
                Mail::to($this->previousAssignee->email)
                    ->send(new TicketAssigned($this->ticket, $this->assignee, 'previous_assignee'));
            }
        } catch (\Exception $e) {
            Log::error('Failed to send ticket assigned emails', [
                'ticket_id' => $this->ticket->id,
                'assignee_id' => $this->assignee->id,
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
        Log::error('SendTicketAssignedEmail job failed after all retries', [
            'ticket_id' => $this->ticket->id,
            'assignee_id' => $this->assignee->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
