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
use App\Models\User;

class PostMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public Post $post,
        public User $user
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->post->title,
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
            html: 'emails.post',
            text: 'emails.post-text',
            with: [
                'post' => $this->post,
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
        // Get or create post subscription for this user
        $subscription = $this->user->subscribeTo('post');

        return route('unsubscribe.show', $subscription->unsubscribe_token);
    }

    /**
     * Generate view online URL
     */
    private function generateViewOnlineUrl(): string
    {
        if ($this->post->is_published_as_blog) {
            return route('post.show', $this->post->slug);
        }

        // Generate a secure view link for private post
        return route('post.view', [
            'uuid' => $this->post->uuid,
            'token' => hash('sha256', $this->post->uuid . $this->user->email . config('app.key'))
        ]);
    }
}
