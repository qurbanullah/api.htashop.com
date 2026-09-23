<?php

namespace App\Mail\Post;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Queue\SerializesModels;
use App\Models\Post;
use App\Models\Subscribe;
use App\Models\User;

class PostMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * `$user` is a registered User for account subscriptions, or a Subscribe
     * row (guest newsletter list, type = newsletter) carrying the email.
     */
    public function __construct(
        public Post $post,
        public User|Subscribe $user
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->post->title,
            from: new Address(
                address: (string) config('mail.from.address', 'noreply@example.com'),
                name: (string) config('mail.from.name', 'HTAShop'),
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
            html: 'emails.post',
            text: 'emails.post-text',
            with: [
                'post' => $this->post,
                'user' => $this->user,
                'recipientName' => $this->recipientName(),
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
     * Personalize the greeting — name for accounts, email for guest lists.
     */
    private function recipientName(): ?string
    {
        if ($this->user instanceof User) {
            return $this->user->name ?: $this->user->email;
        }

        return $this->user->email;
    }

    /**
     * Generate unsubscribe URL.
     *
     * Newsletter (guest) rows go to the storefront SPA unsubscribe page;
     * account-based post subscriptions keep the token page on this host.
     */
    private function generateUnsubscribeUrl(): string
    {
        if ($this->user instanceof Subscribe) {
            $token = $this->user->unsubscribe_token;

            return $token
                ? rtrim((string) config('app.frontend_url', 'https://htashop.com'), '/')
                    . '/unsubscribe/' . $token
                : rtrim((string) config('app.frontend_url', 'https://htashop.com'), '/');
        }

        // Get or create post subscription for this user
        $subscription = $this->user->subscribeTo('post');

        return route('unsubscribe.show', $subscription->unsubscribe_token);
    }

    /**
     * Generate view online URL.
     */
    private function generateViewOnlineUrl(): string
    {
        if ($this->post->is_published_as_blog) {
            return route('post.show', $this->post->slug);
        }

        $email = $this->user->email ?? 'guest';

        // Generate a secure view link for private post
        return route('post.view', [
            'uuid' => $this->post->uuid,
            'token' => hash('sha256', $this->post->uuid . $email . config('app.key')),
        ]);
    }
}
