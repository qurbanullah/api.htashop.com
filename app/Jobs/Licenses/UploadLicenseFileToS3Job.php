<?php

declare(strict_types=1);

namespace App\Jobs\Licenses;

use App\Models\License;
use App\Services\Storage\S3UploadService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class UploadLicenseFileToS3Job implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 5;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 300;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    /**
     * Calculate the number of seconds to wait before retrying the job.
     * Uses exponential backoff: 30s, 60s, 120s, 240s, 480s
     */
    public function backoff(): array
    {
        return [30, 60, 120, 240, 480];
    }

    /**
     * Delete the job if its models no longer exist.
     */
    public bool $deleteWhenMissingModels = true;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $licenseId,
        public string $localPath,
        public string $s3Path,
        public string $fileType // 'data_file' or 'license_file'
    ) {}

    /**
     * Execute the job.
     */
    public function handle(S3UploadService $s3UploadService): void
    {
        try {
            // Find the license
            $license = License::find($this->licenseId);
            // Ensure we have the latest database state in case the job was serialized earlier
            if ($license) {
                $license->refresh();
            }
            if (!$license) {
                Log::warning('License not found for S3 upload', [
                    'license_id' => $this->licenseId,
                    'file_type' => $this->fileType,
                ]);
                return;
            }

            // Resolve local file path and provide aggressive fallbacks if missing
            $disk = Storage::disk('private');
            $fullPath = null;

            // Primary check: disk exists
            if ($disk->exists($this->localPath)) {
                $fullPath = $disk->path($this->localPath);
            } else {
                // Secondary check: try resolving absolute path and file_exists
                try {
                    $candidate = $disk->path($this->localPath);
                    if ($candidate && file_exists($candidate)) {
                        $fullPath = $candidate;
                    }
                } catch (\Exception $e) {
                    // ignore and continue to fuzzy search
                }
            }

            // Tertiary fallback: fuzzy-match by basename in the expected directory
            if (!$fullPath) {
                try {
                    $dir = dirname($this->localPath);
                    $base = pathinfo($this->localPath, PATHINFO_BASENAME);
                    $storageRoot = $disk->path('');
                    $searchDir = rtrim($storageRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($dir, DIRECTORY_SEPARATOR);

                    if (is_dir($searchDir)) {
                        $files = scandir($searchDir);
                        foreach ($files as $f) {
                            if ($f === '.' || $f === '..') continue;
                            // match by exact basename or file name starts with base filename (without timestamp)
                            if ($f === $base || strpos($f, pathinfo($base, PATHINFO_FILENAME)) === 0) {
                                $fullPath = $searchDir . DIRECTORY_SEPARATOR . $f;
                                break;
                            }
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning('Fuzzy search failed during S3 upload', [
                        'license_id' => $this->licenseId,
                        'local_path' => $this->localPath,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            if (!$fullPath || !file_exists($fullPath)) {
                // If file missing, attempt re-dispatch with a delay before giving up
                $maxRescheduleAttempts = 3;
                $attempts = method_exists($this, 'attempts') ? $this->attempts() : 0;

                if ($attempts < $maxRescheduleAttempts) {
                    $delaySeconds = 30 * ($attempts + 1);
                    Log::warning('Local file not found for S3 upload; re-dispatching with delay', [
                        'license_id' => $this->licenseId,
                        'local_path' => $this->localPath,
                        'resolved_path' => $fullPath,
                        'file_type' => $this->fileType,
                        'attempts' => $attempts,
                        'delay_seconds' => $delaySeconds,
                    ]);

                    self::dispatch($this->licenseId, $this->localPath, $this->s3Path, $this->fileType)
                        ->delay(now()->addSeconds($delaySeconds));

                    return;
                }

                Log::error('Local file not found for S3 upload (after fallbacks and retries)', [
                    'license_id' => $this->licenseId,
                    'local_path' => $this->localPath,
                    'resolved_path' => $fullPath,
                    'file_type' => $this->fileType,
                    'attempts' => $attempts,
                ]);

                return;
            }

            Log::info('Starting S3 upload', [
                'license_id' => $this->licenseId,
                'file_type' => $this->fileType,
                'local_path' => $this->localPath,
                'resolved_path' => $fullPath,
                's3_path' => $this->s3Path,
                'file_size' => @filesize($fullPath),
            ]);

            // Upload to S3 directly from the file path (no temp file needed)
            $s3Key = $s3UploadService->uploadFile(
                $fullPath,
                $this->s3Path
            );

            // Update the license with S3 key
            $columnName = $this->fileType === 'data_file' ? 'data_file_s3_key' : 'license_file_s3_key';
            $license->update([
                $columnName => $s3Key,
            ]);

            Log::info('License file uploaded to S3 successfully', [
                'license_id' => $this->licenseId,
                'file_type' => $this->fileType,
                's3_key' => $s3Key,
                'local_path' => $this->localPath,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to upload license file to S3', [
                'license_id' => $this->licenseId,
                'file_type' => $this->fileType,
                'local_path' => $this->localPath,
                's3_path' => $this->s3Path,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-throw to allow retry
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('License file S3 upload job failed after all retries', [
            'license_id' => $this->licenseId,
            'file_type' => $this->fileType,
            'local_path' => $this->localPath,
            's3_path' => $this->s3Path,
            'error' => $exception->getMessage(),
        ]);
    }
}
