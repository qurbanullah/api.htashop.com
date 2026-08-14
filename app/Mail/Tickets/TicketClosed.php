<?php

declare(strict_types=1);

namespace App\Mail\Tickets;

use App\Mail\Tickets\Concerns\BuildsTicketUrl;
use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TicketClosed extends Mailable
{
    use BuildsTicketUrl, Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public Ticket $ticket,
        public string $recipientType = 'creator' // 'creator', 'assignee'
    ) {
        //
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Ticket Closed - ' . $this->ticket->title,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.tickets.closed',
            with: [
                'ticket' => $this->ticket,
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
