<?php

namespace App\Mail;

use App\Models\QuoteRequest;
use App\Models\QuoteResponse;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class QuoteResponseMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public QuoteRequest $quoteRequest,
        public QuoteResponse $quoteResponse,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->quoteResponse->subject ?: 'Your quote',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.quote-response',
            with: [
                'quoteRequest' => $this->quoteRequest,
                'quoteResponse' => $this->quoteResponse,
            ],
        );
    }
}
