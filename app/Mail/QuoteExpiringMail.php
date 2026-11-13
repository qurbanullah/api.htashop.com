<?php

namespace App\Mail;

use App\Models\QuoteRequest;
use App\Models\QuoteResponse;
use App\Support\Quotes\QuotePortalLink;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Buyer reminder sent a few days before a revision's validity window closes.
 */
class QuoteExpiringMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public QuoteRequest $quoteRequest,
        public QuoteResponse $quoteResponse,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: sprintf(
                'Your quote %s expires on %s',
                $this->quoteRequest->quote_number ?? '',
                $this->quoteResponse->expires_at?->format('d M Y') ?? 'soon',
            ),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.quote-expiring',
            with: [
                'quoteRequest' => $this->quoteRequest,
                'quoteResponse' => $this->quoteResponse,
                'portalUrl' => QuotePortalLink::for($this->quoteRequest, $this->quoteResponse),
            ],
        );
    }
}
