<?php

declare(strict_types=1);

namespace App\Jobs\Licenses;

use App\Helpers\AdminHelper;
use App\Mail\LicenseRequestCreatedMail;
use App\Models\License;
use App\Models\User;
use App\Services\Email\EmailLogService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;

class SendLicenseRequestCreatedEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public License $license;
    public ?int $triggeredByUserId = null;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 120;

    /**
     * Create a new job instance.
     */
    public function __construct(License $license, ?int $triggeredByUserId = null)
    {
        $this->license = $license;
        $this->triggeredByUserId = $triggeredByUserId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Load necessary relationships
            $this->license->load(['user', 'software', 'version', 'package', 'ltype']);

            $emailLogService = new EmailLogService();

            // Determine requester and approver (approver may be null for request creation)
            $requestedBy = $this->license->user;
            $approvedBy = null;
            if (!empty($this->triggeredByUserId)) {
                $approvedBy = User::find($this->triggeredByUserId);
            }

            // Send email to the user who created the request (include requested_by)
            $mailableForUser = new LicenseRequestCreatedMail($this->license, 'user');
            $mailableForUser->with(['requested_by' => $requestedBy, 'approved_by' => $approvedBy]);

            $userEmailLog = $emailLogService->createLog(
                $this->license->user->email,
                $this->license->user->name,
                $mailableForUser,
                'license',
                $this->license->id,
                ['action' => 'request_created'],
                $this->license->user->id
            );

            try {
                Mail::to($this->license->user->email)
                    ->send($mailableForUser);
                $emailLogService->markAsSent($userEmailLog);

                Log::info('License request confirmation email sent to user', [
                    'license_id' => $this->license->id,
                    'user_email' => $this->license->user->email
                ]);
            } catch (\Exception $e) {
                $emailLogService->markAsFailed($userEmailLog, $e);
                Log::warning('Failed to send confirmation email to user', [
                    'user_email' => $this->license->user->email,
                    'error' => $e->getMessage(),
                ]);
            }

            // Send email to admins (exclude the license requester, cached)
            $admins = AdminHelper::getAdminsExcludingUser($this->license->user->id);

            foreach ($admins as $admin) {
                $adminEmailLog = $emailLogService->createLog(
                    $admin->email,
                    $admin->name,
                    new LicenseRequestCreatedMail($this->license, 'admin'),
                    'license',
                    $this->license->id,
                    ['action' => 'request_created', 'recipient_type' => 'admin'],
                    $admin->id
                );

                try {
                    Mail::to($admin->email)
                        ->send(new LicenseRequestCreatedMail($this->license, 'admin'));
                    $emailLogService->markAsSent($adminEmailLog);
                } catch (\Exception $e) {
                    $emailLogService->markAsFailed($adminEmailLog, $e);
                    Log::warning('Failed to send license request email to admin', [
                        'admin_email' => $admin->email,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Mail::to('qurbanullah@volvicon.com')
            //     ->send(new LicenseRequestCreatedMail($this->license, 'admin'));

            // Mail::to('furqan@volvicon.com')
            //     ->send(new LicenseRequestCreatedMail($this->license, 'admin'));

            Log::info('License request notification emails sent to admins', [
                'license_id' => $this->license->id,
                'admin_count' => $admins->count()
            ]);

            // Send email to sales managers
            // $salesManagers = User::role('sales_manager')->get();
            // foreach ($salesManagers as $salesManager) {
            //     Mail::to($salesManager->email)
            //         ->send(new LicenseRequestCreatedMail($this->license, 'sales_manager'));
            // }

            // Log::info('License request notification emails sent to sales managers', [
            //     'license_id' => $this->license->id,
            //     'sales_manager_count' => $salesManagers->count()
            // ]);

        } catch (\Exception $e) {
            Log::error('Failed to send license request notification emails', [
                'license_id' => $this->license->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Re-throw the exception to trigger job failure and retry
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('License request email job failed permanently', [
            'license_id' => $this->license->id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
}
