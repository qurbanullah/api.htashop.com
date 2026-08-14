<?php

declare(strict_types=1);

namespace App\Mail\Tickets;

use App\Mail\Tickets\Concerns\BuildsTicketUrl;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TicketUnassigned extends Mailable
{
    use BuildsTicketUrl, Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public Ticket $ticket,
        public User $previousAssignee,
        public string $recipientType = 'previous_assignee' // 'previous_assignee', 'creator', 'support'
    ) {
        //
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = match($this->recipientType) {
            'previous_assignee' => 'You\'ve Been Unassigned from Ticket - ' . $this->ticket->title,
            'creator' => 'Ticket Unassigned - ' . $this->ticket->title,
            'support' => 'Unassigned Ticket Requires Action - ' . $this->ticket->title,
            default => 'Ticket Unassignment Update - ' . $this->ticket->title,
        };

        return new Envelope(
            subject: $subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.tickets.unassigned',
            with: [
                'ticket' => $this->ticket,
                'previousAssignee' => $this->previousAssignee,
                'recipientType' => $this->recipientType,
                'ticketUrl' => $this->buildTicketUrl($this->ticket, $this->recipientType),
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
