<?php

declare(strict_types=1);

namespace App\Jobs\Tickets;

use App\Helpers\AdminHelper;
use App\Mail\Tickets\TicketUnassigned;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendTicketUnassignedEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Ticket $ticket,
        public User $previousAssignee
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Send email to previous assignee
            if ($this->previousAssignee->email) {
                Mail::to($this->previousAssignee->email)
                    ->send(new TicketUnassigned($this->ticket, $this->previousAssignee, 'previous_assignee'));
            }

            // Send email to ticket creator
            if ($this->ticket->submitter_email) {
                Mail::to($this->ticket->submitter_email)
                    ->send(new TicketUnassigned($this->ticket, $this->previousAssignee, 'creator'));
            }

            // Send alert to support managers
            // Using AdminHelper to get cached managers and avoid duplicates
            $supportManagers = AdminHelper::getManagersExcludingUser($this->previousAssignee->id);

            foreach ($supportManagers as $manager) {
                if ($manager->email) {
                    Mail::to($manager->email)
                        ->send(new TicketUnassigned($this->ticket, $this->previousAssignee, 'support'));
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to send ticket unassigned emails', [
                'ticket_id' => $this->ticket->id,
                'previous_assignee_id' => $this->previousAssignee->id,
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
        Log::error('SendTicketUnassignedEmail job failed after all retries', [
            'ticket_id' => $this->ticket->id,
            'previous_assignee_id' => $this->previousAssignee->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
