<?php

declare(strict_types=1);

namespace App\Jobs\Tickets;

use App\Mail\Tickets\TicketResolved;
use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendTicketResolvedEmail implements ShouldQueue
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

            // Send email to ticket creator
            if ($this->ticket->submitter_email) {
                Mail::to($this->ticket->submitter_email)
                    ->send(new TicketResolved($this->ticket, 'creator'));
            }

            // Send confirmation to assigned user if exists
            $assignment = $this->ticket->activeAssignments->first();
            if ($assignment && $assignment->assignedTo && $assignment->assignedTo->email) {
                Mail::to($assignment->assignedTo->email)
                    ->send(new TicketResolved($this->ticket, 'assignee'));
            }
        } catch (\Exception $e) {
            Log::error('Failed to send ticket resolved emails', [
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
        Log::error('SendTicketResolvedEmail job failed after all retries', [
            'ticket_id' => $this->ticket->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
