<?php

declare(strict_types=1);

namespace App\Jobs\Tickets;

use App\Mail\Tickets\TicketLocked;
use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendTicketLockedEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Ticket $ticket,
        public bool $isLocked
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

            // Send email to ticket creator
            if ($this->ticket->submitter_email) {
                Mail::to($this->ticket->submitter_email)
                    ->send(new TicketLocked($this->ticket, $this->isLocked, 'creator'));
            }

            // Send notification to assigned user if exists
            $assignment = $this->ticket->activeAssignments->first();
            if ($assignment && $assignment->assignedTo && $assignment->assignedTo->email) {
                Mail::to($assignment->assignedTo->email)
                    ->send(new TicketLocked($this->ticket, $this->isLocked, 'assignee'));
            }
        } catch (\Exception $e) {
            Log::error('Failed to send ticket locked emails', [
                'ticket_id' => $this->ticket->id,
                'is_locked' => $this->isLocked,
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
        Log::error('SendTicketLockedEmail job failed after all retries', [
            'ticket_id' => $this->ticket->id,
            'is_locked' => $this->isLocked,
            'error' => $exception->getMessage(),
        ]);
    }
}
