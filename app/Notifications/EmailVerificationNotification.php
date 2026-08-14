<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\User;

class EmailVerificationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        protected User $user,
        protected string $verificationToken
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $verificationUrl = $this->getVerificationUrl();
        $appName = config('app.name', 'Volvicon');

        return (new MailMessage)
            ->subject('Verify Your Email Address - ' . $appName)
            ->greeting('Hello ' . $this->user->name . ',')
            ->line('Please verify your email address by clicking the button below.')
            ->action('Verify Email Address', $verificationUrl)
            ->line('This link will expire in 24 hours.')
            ->line('If you did not create an account, you can ignore this email.')
            ->salutation('Best regards,')
            ->from(config('mail.from.address'), config('mail.from.name'));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'user_id' => $this->user->id,
            'email' => $this->user->email,
            'verification_token' => $this->verificationToken,
        ];
    }

    /**
     * Build the verification URL
     */
    private function getVerificationUrl(): string
    {
        $frontendUrl = config('app.manage_frontend_url', 'https://manage.volvicon.com');
        $token = urlencode($this->verificationToken);
        return "{$frontendUrl}/verify-email?token={$token}&email=" . urlencode($this->user->email);
    }
}
