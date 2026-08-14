<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Bus\Queueable;

class ResetPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * The password reset token.
     *
     * @var string
     */
    public $token;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $token)
    {
        $this->token = $token;

        \Illuminate\Support\Facades\Log::info('ResetPasswordNotification created', [
            'token' => substr($token, 0, 10) . '...',
            'queue_connection' => config('queue.default'),
        ]);
    }

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
        \Illuminate\Support\Facades\Log::info('toMail method called', [
            'user_id' => $notifiable->id,
            'email' => $notifiable->email,
        ]);

        // Determine which frontend to use based on cached context
        $frontend = cache()->get('password_reset_frontend_' . $notifiable->id, 'manage');

        \Illuminate\Support\Facades\Log::info('Frontend context retrieved', [
            'user_id' => $notifiable->id,
            'frontend' => $frontend,
            'cache_key' => 'password_reset_frontend_' . $notifiable->id,
        ]);

        // Map frontend to frontend URL (direct to frontend, not through backend)
        $frontendUrl = match($frontend) {
            'admin' => config('app.admin_frontend_url'),
            'main' => config('app.frontend_url'),
            'manage' => config('app.manage_frontend_url'),
            default => config('app.frontend_url'),
        };

        // Fallback if configuration is missing or empty
        if (empty($frontendUrl)) {
            \Illuminate\Support\Facades\Log::warning('Frontend URL missing for password reset, falling back to app.frontend_url', ['frontend' => $frontend]);
            $frontendUrl = config('app.frontend_url');
        }

        // Build the reset URL directly to the frontend
        $resetUrl = rtrim($frontendUrl, '/') . '/reset-password?token=' . $this->token . '&email=' . urlencode($notifiable->getEmailForPasswordReset());

        \Illuminate\Support\Facades\Log::info('Reset URL generated', [
            'user_id' => $notifiable->id,
            'frontend' => $frontend,
            'frontend_url' => $frontendUrl,
            'reset_url' => $resetUrl,
        ]);

        // Clean up the cache
        cache()->forget('password_reset_frontend_' . $notifiable->id);

        $mailMessage = (new MailMessage)
            ->subject('Reset Your Password - ' . config('app.name'))
            ->view('emails.reset-password', [
                'resetUrl' => $resetUrl,
                'userName' => $notifiable->name,
                'email' => $notifiable->email,
            ])
            ->text('emails.reset-password-text', [
                'resetUrl' => $resetUrl,
                'userName' => $notifiable->name,
                'email' => $notifiable->email,
            ]);

        \Illuminate\Support\Facades\Log::info('MailMessage created successfully', [
            'user_id' => $notifiable->id,
            'subject' => 'Reset Your Password - ' . config('app.name'),
        ]);

        return $mailMessage;
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}

