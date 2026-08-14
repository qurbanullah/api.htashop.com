<?php

declare(strict_types=1);

/**
 * Send License Activation Email Job class file.
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
use App\Mail\LicenseActivatedMail;
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
 * Job to send license activation/deactivation email notification
 *
 * @category App\Jobs
 *
 * @author   Qurban Ullah <qurbanullah@gmail.com>
 * @license  CC BY-NC-ND 4.0 Deed https://creativecommons.org/licenses/by-nc-nd/4.0/
 *
 * @link     https://github.com/qurbanullah
 */
class SendLicenseActivationEmailJob implements ShouldQueue
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
        public bool $isActivated,
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

            // Ensure files are uploaded to S3 if necessary before sending emails
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

            $emailLogService = new EmailLogService();
            $statusText = $this->isActivated ? 'activated' : 'deactivated';

            // Determine approver and requester
            $requestedBy = $this->license->user;
            $approvedBy = null;
            if (!empty($this->triggeredByUserId)) {
                $approvedBy = User::find($this->triggeredByUserId);
            }

            // Send the license activation email to user (do NOT include approver info)
            $mailableForUser = new LicenseActivatedMail(
                license: $this->license,
                isActivated: $this->isActivated,
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
                ['action' => $statusText],
                $this->license->user->id
            );

            try {
                Mail::to($this->license->user->email)
                    ->send($mailableForUser);
                $emailLogService->markAsSent($userEmailLog);

                Log::info("License {$statusText} email sent successfully", [
                    'license_id' => $this->license->id,
                    'license_uuid' => $this->license->uuid,
                    'user_email' => $this->license->user->email,
                    'software' => $this->license->software->name,
                    'is_activated' => $this->isActivated,
                ]);
            } catch (\Exception $e) {
                $emailLogService->markAsFailed($userEmailLog, $e);
                throw $e;
            }

            // Send email to admins (exclude the license requester, cached)
            $admins = AdminHelper::getAdminsExcludingUser($this->license->user->id);

            foreach ($admins as $admin) {
                $mailable = new LicenseActivatedMail(
                    license: $this->license,
                    isActivated: $this->isActivated,
                    approvedBy: $approvedBy,
                    requestedBy: $requestedBy,
                    isAdmin: true
                );

                $adminEmailLog = $emailLogService->createLog(
                    $admin->email,
                    $admin->name,
                    $mailable,
                    'license',
                    $this->license->id,
                    ['action' => $statusText, 'recipient_type' => 'admin'],
                    $admin->id
                );

                try {
                    Mail::to($admin->email)
                        ->send($mailable);
                    $emailLogService->markAsSent($adminEmailLog);
                } catch (\Exception $e) {
                    $emailLogService->markAsFailed($adminEmailLog, $e);
                    Log::warning("Failed to send {$statusText} email to admin", [
                        'admin_email' => $admin->email,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Mail::to('qurbanullah@volvicon.com')
            //     ->send(new LicenseActivatedMail($this->license, $this->isActivated));

            // Mail::to('furqan@volvicon.com')
            //     ->send(new LicenseActivatedMail($this->license, $this->isActivated));

            Log::info('License request notification emails sent to admins', [
                'license_id' => $this->license->id,
                'admin_count' => $admins->count()
            ]);

        } catch (\Exception $e) {
            $statusText = $this->isActivated ? 'activated' : 'deactivated';

            Log::error("Failed to send license {$statusText} email", [
                'license_id' => $this->license->id,
                'license_uuid' => $this->license->uuid,
                'user_email' => $this->license->user->email,
                'is_activated' => $this->isActivated,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-throw the exception to trigger job retry
            throw $e;
        }
    }
}
