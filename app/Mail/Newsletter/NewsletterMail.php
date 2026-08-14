<?php

namespace App\Mail\Newsletter;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Queue\SerializesModels;
use App\Models\Newsletter;
use App\Models\User;

class NewsletterMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public Newsletter $newsletter,
        public User $user
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->newsletter->title,
            from: new Address(
                address: config('mail.from.address', 'noreply@example.com'),
                name: 'Real3dtech'
            ),
            replyTo: config('mail.reply_to.address'),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            html: 'emails.newsletter',
            text: 'emails.newsletter-text',
            with: [
                'newsletter' => $this->newsletter,
                'user' => $this->user,
                'unsubscribeUrl' => $this->generateUnsubscribeUrl(),
                'viewOnlineUrl' => $this->generateViewOnlineUrl(),
            ]
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

    /**
     * Generate unsubscribe URL
     */
    private function generateUnsubscribeUrl(): string
    {
        // Get or create newsletter subscription for this user
        $subscription = $this->user->subscribeTo('newsletter');

        return route('unsubscribe.show', $subscription->unsubscribe_token);
    }

    /**
     * Generate view online URL
     */
    private function generateViewOnlineUrl(): string
    {
        if ($this->newsletter->is_published_as_blog) {
            return route('newsletter.show', $this->newsletter->slug);
        }

        // Generate a secure view link for private newsletters
        return route('newsletter.view', [
            'uuid' => $this->newsletter->uuid,
            'token' => hash('sha256', $this->newsletter->uuid . $this->user->email . config('app.key'))
        ]);
    }
}
