<?php

namespace App\Mail;

use App\Models\QuoteRequest;
use App\Models\QuoteResponse;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Internal notification: the buyer accepted or declined a quotation.
 */
class QuoteDecisionMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  'accepted'|'declined'  $decision
     */
    public function __construct(
        public QuoteRequest $quoteRequest,
        public ?QuoteResponse $quoteResponse,
        public string $decision,
        public ?string $reason = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: sprintf(
                'Quote %s was %s',
                $this->quoteRequest->quote_number ?? '',
                $this->decision,
            ),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.quote-decision',
            with: [
                'quoteRequest' => $this->quoteRequest,
                'quoteResponse' => $this->quoteResponse,
                'decision' => $this->decision,
                'reason' => $this->reason,
            ],
        );
    }
}
