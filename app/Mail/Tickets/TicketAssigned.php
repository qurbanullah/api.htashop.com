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

class TicketAssigned extends Mailable
{
    use BuildsTicketUrl, Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public Ticket $ticket,
        public User $assignee,
        public string $recipientType = 'assignee' // 'assignee', 'creator', 'previous_assignee'
    ) {
        //
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = match($this->recipientType) {
            'assignee' => 'You\'ve Been Assigned to Ticket - ' . $this->ticket->title,
            'creator' => 'Your Ticket Has Been Assigned - ' . $this->ticket->title,
            'previous_assignee' => 'Ticket Reassigned - ' . $this->ticket->title,
            default => 'Ticket Assignment Update - ' . $this->ticket->title,
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
            view: 'emails.tickets.assigned',
            with: [
                'ticket' => $this->ticket,
                'assignee' => $this->assignee,
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
