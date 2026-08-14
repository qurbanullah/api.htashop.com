<?php

namespace App\Jobs\Contacts;

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

        // Get admin users, exclude the contact sender (cached)
        $adminUsers = AdminHelper::getAdminsExcludingEmail($this->contactMessage->email);

        // Send notification to all admins
        foreach ($adminUsers as $admin) {
            $adminEmailLog = $emailLogService->createLog(
                $admin->email,
                $admin->name,
                new ContactMessageNotificationMail($this->contactMessage, $admin),
                'contact_message',
                $this->contactMessage->id,
                ['recipient_type' => 'admin'],
                $admin->id
            );

            try {
                Mail::to($admin->email)
                    ->send(new ContactMessageNotificationMail($this->contactMessage, $admin));
                $emailLogService->markAsSent($adminEmailLog);
            } catch (\Exception $e) {
                $emailLogService->markAsFailed($adminEmailLog, $e);
                Log::warning('Failed to send contact message notification to admin', [
                    'admin_email' => $admin->email,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Also send to a general admin email if configured and not already included
        // $adminEmail = config('mail.admin_email', 'qurbanullah@real3dtech.com');
        // if ($adminEmail && !$adminUsers->pluck('email')->contains($adminEmail)) {
        //     $configEmailLog = $emailLogService->createLog(
        //         $adminEmail,
        //         'Admin',
        //         new ContactMessageNotificationMail($this->contactMessage),
        //         'contact_message',
        //         $this->contactMessage->id,
        //         ['recipient_type' => 'configured_admin']
        //     );

        //     try {
        //         Mail::to($adminEmail)
        //             ->send(new ContactMessageNotificationMail($this->contactMessage));
        //         $emailLogService->markAsSent($configEmailLog);
        //     } catch (\Exception $e) {
        //         $emailLogService->markAsFailed($configEmailLog, $e);
        //         Log::warning('Failed to send contact message to configured admin email', [
        //             'admin_email' => $adminEmail,
        //             'error' => $e->getMessage(),
        //         ]);
        //     }
        // }
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
