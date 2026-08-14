<?php

namespace App\Jobs\Version;

use App\Services\Storage\S3UploadService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class DeleteVersionFilesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 120;

    /**
     * Delete the job if its models no longer exist.
     *
     * @var bool
     */
    public $deleteWhenMissingModels = true;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public readonly int $versionId,
        public readonly ?string $filePath,
        public readonly ?string $imagePath,
        public readonly ?array $metadata
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(S3UploadService $s3UploadService): void
    {
        Log::info('DeleteVersionFilesJob: Starting background file deletion', [
            'version_id' => $this->versionId,
            'has_file' => !empty($this->filePath),
            'has_image' => !empty($this->imagePath),
            'has_metadata' => !empty($this->metadata),
        ]);

        // Delete software file
        if ($this->filePath) {
            try {
                Log::info('Deleting software file from S3', ['path' => $this->filePath]);
                $s3UploadService->deleteFile($this->filePath);
                $this->clearFileCache($this->filePath);
                Log::info('Software file deleted successfully');
            } catch (\Exception $e) {
                Log::warning('Failed to delete software file from S3', [
                    'path' => $this->filePath,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Delete image file
        if ($this->imagePath) {
            try {
                Log::info('Deleting image file from S3', ['path' => $this->imagePath]);
                $s3UploadService->deleteFile($this->imagePath);
                $this->clearFileCache($this->imagePath);
                Log::info('Image file deleted successfully');
            } catch (\Exception $e) {
                Log::warning('Failed to delete image file from S3', [
                    'path' => $this->imagePath,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Delete metadata files (terms and privacy)
        if (!empty($this->metadata)) {
            // Delete terms file
            if (isset($this->metadata['terms']['path'])) {
                try {
                    Log::info('Deleting terms file from S3', ['path' => $this->metadata['terms']['path']]);
                    $s3UploadService->deleteFile($this->metadata['terms']['path']);
                    $this->clearFileCache($this->metadata['terms']['path']);
                    Log::info('Terms file deleted successfully');
                } catch (\Exception $e) {
                    Log::warning('Failed to delete terms file from S3', [
                        'path' => $this->metadata['terms']['path'],
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Delete privacy file
            if (isset($this->metadata['privacy']['path'])) {
                try {
                    Log::info('Deleting privacy file from S3', ['path' => $this->metadata['privacy']['path']]);
                    $s3UploadService->deleteFile($this->metadata['privacy']['path']);
                    $this->clearFileCache($this->metadata['privacy']['path']);
                    Log::info('Privacy file deleted successfully');
                } catch (\Exception $e) {
                    Log::warning('Failed to delete privacy file from S3', [
                        'path' => $this->metadata['privacy']['path'],
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        Log::info('DeleteVersionFilesJob: Completed', ['version_id' => $this->versionId]);
    }

    /**
     * Clear file existence cache for a specific path
     */
    private function clearFileCache(string $path): void
    {
        $cacheKey = 's3_file_exists_' . md5($path);
        Cache::forget($cacheKey);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('DeleteVersionFilesJob: Failed to delete version files', [
            'version_id' => $this->versionId,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
