<?php

declare(strict_types=1);

/**
 * Send License Approval Email Job class file.
 * php version 8.4
 *
 * @category  App\Jobs
 *
 * @author    Qurban Ullah <qurbanullah@gmail.com>
 * @copyright 2024 Qurban Ullah - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by Qurban Ullah <qurbanullah@gmail.com>, 2024
 * @license   CC BY-NC-ND 4.0 Deed https://creativecommons.org/licenses/by-nc-nd/4.0/
 *
 * @version   GIT: <git_id>
 *
 * @link      https://github.com/qurbanullah
 */

namespace App\Jobs\Licenses;

use App\Helpers\AdminHelper;
use App\Mail\LicenseApprovedMail;
use App\Models\User;
use App\Models\License;
use App\Services\Email\EmailLogService;
use App\Services\Storage\S3UploadService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Job to send license approval email notification
 *
 * @category App\Jobs
 *
 * @author   Qurban Ullah <qurbanullah@gmail.com>
 * @license  CC BY-NC-ND 4.0 Deed https://creativecommons.org/licenses/by-nc-nd/4.0/
 *
 * @link     https://github.com/qurbanullah
 */
class SendLicenseApprovalEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The maximum number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 120;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public License $license,
        public ?int $triggeredByUserId = null
    ) {
        // Ensure the license relationships are loaded
        $this->license->load(['user', 'software', 'version', 'package', 'ltype']);
    }

    /**
     * Execute the job.
     */
    public function handle(S3UploadService $s3UploadService): void
    {
        try {
            // Refresh the license from database to get latest file paths
            // (files may have been generated after job was queued)
            $this->license->refresh();
            $this->license->load(['user', 'software', 'version', 'package', 'ltype']);

            $emailLogService = new EmailLogService();

            // Determine approver and requester
            $requestedBy = $this->license->user;
            $approvedBy = null;
            if (!empty($this->triggeredByUserId)) {
                $approvedBy = User::find($this->triggeredByUserId);
            }

            // Ensure files are uploaded to S3 if running in a multi-node environment
            try {
                $disk = Storage::disk('private');

                // License file
                if (!empty($this->license->license_file) && empty($this->license->license_file_s3_key)) {
                    $localPath = $this->license->license_file;
                    $fullPath = null;
                    if ($disk->exists($localPath)) {
                        $fullPath = $disk->path($localPath);
                    } else {
                        try {
                            $candidate = $disk->path($localPath);
                            if ($candidate && file_exists($candidate)) {
                                $fullPath = $candidate;
                            }
                        } catch (\Exception $e) {
                        }
                    }

                    // fuzzy
                    if (!$fullPath) {
                        $dir = dirname($localPath);
                        $storageRoot = $disk->path('');
                        $searchDir = rtrim($storageRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($dir, DIRECTORY_SEPARATOR);
                        if (is_dir($searchDir)) {
                            foreach (scandir($searchDir) as $f) {
                                if ($f === '.' || $f === '..') continue;
                                if (strpos($f, pathinfo($localPath, PATHINFO_FILENAME)) === 0) {
                                    $fullPath = $searchDir . DIRECTORY_SEPARATOR . $f;
                                    break;
                                }
                            }
                        }
                    }

                    if ($fullPath && file_exists($fullPath)) {
                        $s3Key = $s3UploadService->uploadFile($fullPath, $localPath);
                        $this->license->update(['license_file_s3_key' => $s3Key]);
                        Log::info('Synchronous S3 upload performed before email (license_file)', ['license_id' => $this->license->id, 's3_key' => $s3Key]);
                    }
                }

                // Data file
                if (!empty($this->license->data_file) && empty($this->license->data_file_s3_key)) {
                    $localPath = $this->license->data_file;
                    $fullPath = null;
                    if ($disk->exists($localPath)) {
                        $fullPath = $disk->path($localPath);
                    } else {
                        try {
                            $candidate = $disk->path($localPath);
                            if ($candidate && file_exists($candidate)) {
                                $fullPath = $candidate;
                            }
                        } catch (\Exception $e) {
                        }
                    }

                    if (!$fullPath) {
                        $dir = dirname($localPath);
                        $storageRoot = $disk->path('');
                        $searchDir = rtrim($storageRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($dir, DIRECTORY_SEPARATOR);
                        if (is_dir($searchDir)) {
                            foreach (scandir($searchDir) as $f) {
                                if ($f === '.' || $f === '..') continue;
                                if (strpos($f, pathinfo($localPath, PATHINFO_FILENAME)) === 0) {
                                    $fullPath = $searchDir . DIRECTORY_SEPARATOR . $f;
                                    break;
                                }
                            }
                        }
                    }

                    if ($fullPath && file_exists($fullPath)) {
                        $s3Key = $s3UploadService->uploadFile($fullPath, $localPath);
                        $this->license->update(['data_file_s3_key' => $s3Key]);
                        Log::info('Synchronous S3 upload performed before email (data_file)', ['license_id' => $this->license->id, 's3_key' => $s3Key]);
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Synchronous S3 upload before email failed', ['license_id' => $this->license->id, 'error' => $e->getMessage()]);
            }

            // Send the license approval email to user (do NOT include approver info)
            $mailableForUser = new LicenseApprovedMail(
                license: $this->license,
                approvedBy: null,
                requestedBy: $requestedBy,
                isAdmin: false
            );

            $userEmailLog = $emailLogService->createLog(
                $this->license->user->email,
                $this->license->user->name,
                $mailableForUser,
                'license',
                $this->license->id,
                ['action' => 'approved'],
                $this->license->user->id
            );

            try {
                Mail::to($this->license->user->email)
                    ->send($mailableForUser);
                $emailLogService->markAsSent($userEmailLog);

                Log::info('License approval email sent successfully', [
                    'license_id' => $this->license->id,
                    'license_uuid' => $this->license->uuid,
                    'user_email' => $this->license->user->email,
                    'software' => $this->license->software->name,
                ]);
            } catch (\Exception $e) {
                $emailLogService->markAsFailed($userEmailLog, $e);
                throw $e;
            }

            // Send email to admins (exclude the license requester, cached)
            $admins = AdminHelper::getAdminsExcludingUser($this->license->user->id);

            foreach ($admins as $admin) {
                // Prepare approver/requester context for admin mailable
                $mailableForAdmin = new LicenseApprovedMail(
                    license: $this->license,
                    approvedBy: $approvedBy,
                    requestedBy: $requestedBy,
                    isAdmin: true
                );

                $adminEmailLog = $emailLogService->createLog(
                    $admin->email,
                    $admin->name,
                    $mailableForAdmin,
                    'license',
                    $this->license->id,
                    ['action' => 'approved', 'recipient_type' => 'admin'],
                    $admin->id
                );

                try {
                    Mail::to($admin->email)->send($mailableForAdmin);
                    $emailLogService->markAsSent($adminEmailLog);
                } catch (\Exception $e) {
                    $emailLogService->markAsFailed($adminEmailLog, $e);
                    Log::warning('Failed to send license approval email to admin', [
                        'admin_email' => $admin->email,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Also send to configured admin emails if not already included
            $configuredAdminEmail = config('mail.admin_email', 'qurbanullah@volvicon.com');
            // $fallbackAdminEmail = 'furqan797@gmail.com'; // Removed - no longer sending to fallback email

            if ($configuredAdminEmail && !$admins->pluck('email')->contains($configuredAdminEmail) && $configuredAdminEmail !== $this->license->user->email) {
                $mailable = new LicenseApprovedMail($this->license);
                $mailable->with(['approved_by' => $approvedBy, 'requested_by' => $requestedBy, 'is_admin' => true]);

                $configEmailLog = $emailLogService->createLog(
                    $configuredAdminEmail,
                    'Admin',
                    $mailable,
                    'license',
                    $this->license->id,
                    ['action' => 'approved', 'recipient_type' => 'configured_admin']
                );

                try {
                    Mail::to($configuredAdminEmail)->send($mailable);
                    $emailLogService->markAsSent($configEmailLog);
                } catch (\Exception $e) {
                    $emailLogService->markAsFailed($configEmailLog, $e);
                }
            }

            // Fallback admin email removed - no longer needed
            // if ($fallbackAdminEmail && !$admins->pluck('email')->contains($fallbackAdminEmail) && $fallbackAdminEmail !== $this->license->user->email) {
            //     $mailable = new LicenseApprovedMail($this->license);
            //     $mailable->with(['approved_by' => $approvedBy, 'requested_by' => $requestedBy, 'is_admin' => true]);

            //     $fallbackEmailLog = $emailLogService->createLog(
            //         $fallbackAdminEmail,
            //         'Admin',
            //         $mailable,
            //         'license',
            //         $this->license->id,
            //         ['action' => 'approved', 'recipient_type' => 'fallback_admin']
            //     );

            //     try {
            //         Mail::to($fallbackAdminEmail)->send($mailable);
            //         $emailLogService->markAsSent($fallbackEmailLog);
            //     } catch (\Exception $e) {
            //         $emailLogService->markAsFailed($fallbackEmailLog, $e);
            //     }
            // }

            Log::info('License approval notification emails sent to admins', [
                'license_id' => $this->license->id,
                'admin_count' => $admins->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send license approval email', [
                'license_id' => $this->license->id,
                'license_uuid' => $this->license->uuid,
                'user_email' => $this->license->user->email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-throw the exception to trigger job retry
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('License approval email job failed after all attempts', [
            'license_id' => $this->license->id,
            'license_uuid' => $this->license->uuid,
            'user_email' => $this->license->user->email,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
