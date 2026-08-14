<?php

namespace App\Mail\Feedback;

use App\Models\Comment;
use App\Models\Feedback;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Queue\SerializesModels;

/**
 * Mail class for feedback comment notifications
 */
class FeedbackCommentMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public Feedback $feedback,
        public Comment $comment
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $commenterName = $this->comment->user->name ?? 'Volvicon Support';
        $commenterEmail = $this->comment->user->email ?? config('mail.from.address');

        return new Envelope(
            replyTo: [
                new Address($commenterEmail, $commenterName),
            ],
            subject: "Re: {$this->feedback->subject}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.feedback.comment',
            with: [
                'feedback' => $this->feedback,
                'comment' => $this->comment,
                'commenterName' => $this->comment->user->name ?? 'Volvicon Support',
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
}
