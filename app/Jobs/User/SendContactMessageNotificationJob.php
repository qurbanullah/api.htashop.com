<?php

namespace App\Jobs\User;

use App\Helpers\AdminHelper;
use App\Mail\Messages\ContactMessageNotificationMail;
use App\Models\ContactMessage;
use App\Models\User;
use App\Services\Email\EmailLogService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendContactMessageNotificationJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public ContactMessage $contactMessage
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $emailLogService = new EmailLogService();
        $configuredRecipient = trim((string) config('mail.contact_recipient_email', ''));

        // Get admin users, exclude the contact sender (cached)
        $adminUsers = AdminHelper::getAdminsExcludingEmail($this->contactMessage->email);

        // Send notification to all admins
        foreach ($adminUsers as $admin) {
            $adminEmail = trim((string) $admin->email);

            if ($adminEmail === '') {
                continue;
            }

            $adminEmailLog = $emailLogService->createLog(
                $adminEmail,
                $admin->name,
                new ContactMessageNotificationMail($this->contactMessage, $admin),
                'contact_message',
                $this->contactMessage->id,
                ['recipient_type' => 'admin'],
                $admin->id
            );

            try {
                Mail::to($adminEmail)
                    ->send(new ContactMessageNotificationMail($this->contactMessage, $admin));
                $emailLogService->markAsSent($adminEmailLog);
            } catch (\Exception $e) {
                $emailLogService->markAsFailed($adminEmailLog, $e);
                Log::warning('Failed to send contact message notification to admin', [
                    'admin_email' => $adminEmail,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if (
            $configuredRecipient
            && strtolower($configuredRecipient) !== strtolower($this->contactMessage->email)
            && !$adminUsers->pluck('email')->contains(fn ($email) => strtolower((string) $email) === strtolower((string) $configuredRecipient))
        ) {
            $configuredEmailLog = $emailLogService->createLog(
                $configuredRecipient,
                'Contact Inbox',
                new ContactMessageNotificationMail($this->contactMessage),
                'contact_message',
                $this->contactMessage->id,
                ['recipient_type' => 'configured_contact_recipient']
            );

            try {
                Mail::to($configuredRecipient)
                    ->send(new ContactMessageNotificationMail($this->contactMessage));
                $emailLogService->markAsSent($configuredEmailLog);
            } catch (\Exception $e) {
                $emailLogService->markAsFailed($configuredEmailLog, $e);
                Log::warning('Failed to send contact message to configured recipient', [
                    'recipient_email' => $configuredRecipient,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        // Log the failure
        Log::error('Failed to send contact message notification', [
            'contact_message_id' => $this->contactMessage->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
