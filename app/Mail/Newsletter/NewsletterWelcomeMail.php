<?php

namespace App\Mail\Newsletter;

use App\Models\Subscribe;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Queue\SerializesModels;

/**
 * Sent when an email joins the store newsletter list.
 */
class NewsletterWelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public Subscribe $subscription,
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(
                config('mail.from.address'),
                config('mail.from.name'),
            ),
            subject: 'You are subscribed to HTAShop updates',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.newsletter-welcome',
            with: [
                'subscription' => $this->subscription,
                'email' => $this->subscription->email,
                'siteUrl' => rtrim((string) config('app.frontend_url', 'https://htashop.com'), '/'),
                'unsubscribeUrl' => rtrim((string) config('app.frontend_url', 'https://htashop.com'), '/')
                    . '/unsubscribe/' . $this->subscription->unsubscribe_token,
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
