<?php

declare(strict_types=1);

namespace App\Mail\Tickets;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TicketCreatedAdmin extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public Ticket $ticket
    ) {
        //
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New Support Ticket - ' . $this->ticket->title,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $adminUrl = config('app.admin_url');
        if (!str_starts_with($adminUrl, 'http')) {
            $adminUrl = 'https://' . $adminUrl;
        }

        return new Content(
            view: 'emails.tickets.created-admin',
            with: [
                'ticket' => $this->ticket,
                'ticketUrl' => rtrim($adminUrl, '/') . '/tickets/' . $this->ticket->uuid,
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
