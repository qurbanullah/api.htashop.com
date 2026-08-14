<?php

declare(strict_types=1);

namespace App\Services\Storage;

use Aws\S3\S3Client;
use Aws\Exception\AwsException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

/**
 * Service for handling direct-to-S3 uploads using pre-signed URLs
 * Supports both simple and multipart uploads
 */
class S3UploadService
{
    protected S3Client $s3Client;
    protected string $bucket;
    protected string $region;

    public function __construct()
    {
        $this->bucket = config('filesystems.disks.idrivee2.bucket');
        $this->region = config('filesystems.disks.idrivee2.region');

        $this->s3Client = new S3Client([
            'version' => 'latest',
            'region' => $this->region,
            'endpoint' => config('filesystems.disks.idrivee2.endpoint'),
            'use_path_style_endpoint' => config('filesystems.disks.idrivee2.use_path_style_endpoint'),
            'credentials' => [
                'key' => config('filesystems.disks.idrivee2.key'),
                'secret' => config('filesystems.disks.idrivee2.secret'),
            ],
            'http' => [
                'timeout' => 10, // 10 seconds for head/exists operations (reduced from 120)
                'connect_timeout' => 5, // 5 seconds for connection establishment (reduced from 30)
                'verify' => true, // Verify SSL certificates
            ],
            'retries' => [
                'mode' => 'adaptive', // Use adaptive retry mode
                'max_attempts' => 1, // Disable AWS SDK retries, we handle it ourselves
            ],
        ]);
    }

    /**
     * Generate a pre-signed URL for direct upload to S3
     *
     * @param string $key The S3 object key (path)
     * @param string $contentType The MIME type of the file
     * @param int $expiresIn Expiration time in seconds (default: 1 hour)
     * @param int|null $maxFileSize Maximum file size in bytes
     * @param array $metadata Additional metadata to set on the object
     * @return array ['url' => string, 'key' => string, 'expires_at' => string]
     */
    public function generatePresignedUploadUrl(
        string $key,
        string $contentType,
        int $expiresIn = 3600,
        ?int $maxFileSize = null,
        array $metadata = []
    ): array {
        try {
            $params = [
                'Bucket' => $this->bucket,
                'Key' => $key,
                'ContentType' => $contentType,
            ];

            $cacheControl = null;

            // Add any additional metadata
            foreach ($metadata as $metaKey => $metaValue) {
                $params[$metaKey] = $metaValue;
            }

            $command = $this->s3Client->getCommand('PutObject', $params);

            $request = $this->s3Client->createPresignedRequest($command, "+{$expiresIn} seconds");
            $presignedUrl = (string) $request->getUri();

            $response = [
                'url' => $presignedUrl,
                'key' => $key,
                'bucket' => $this->bucket,
                'content_type' => $contentType,
                'expires_at' => Carbon::now()->addSeconds($expiresIn)->toIso8601String(),
                'max_file_size' => $maxFileSize,
            ];

            // Include cache_control in response so frontend can set the header
            if ($cacheControl) {
                $response['cache_control'] = $cacheControl;
            }

            return $response;
        } catch (AwsException $e) {
            throw new \Exception("Failed to generate pre-signed URL: " . $e->getMessage());
        }
    }

    /**
     * Initiate a multipart upload for large files
     *
     * @param string $key The S3 object key (path)
     * @param string $contentType The MIME type of the file
     * @return array ['upload_id' => string, 'key' => string]
     */
    public function initiateMultipartUpload(string $key, string $contentType): array
    {
        try {
            $result = $this->s3Client->createMultipartUpload([
                'Bucket' => $this->bucket,
                'Key' => $key,
                'ContentType' => $contentType,
            ]);

            return [
                'upload_id' => $result['UploadId'],
                'key' => $key,
                'bucket' => $this->bucket,
            ];
        } catch (AwsException $e) {
            throw new \Exception("Failed to initiate multipart upload: " . $e->getMessage());
        }
    }

    /**
     * Generate pre-signed URLs for uploading individual parts in a multipart upload
     *
     * @param string $key The S3 object key
     * @param string $uploadId The multipart upload ID
     * @param int $partNumber The part number (1-indexed)
     * @param int $expiresIn Expiration time in seconds
     * @return string The pre-signed URL for uploading this part
     */
    public function generateMultipartUploadUrl(
        string $key,
        string $uploadId,
        int $partNumber,
        int $expiresIn = 3600
    ): string {
        try {
            $command = $this->s3Client->getCommand('UploadPart', [
                'Bucket' => $this->bucket,
                'Key' => $key,
                'UploadId' => $uploadId,
                'PartNumber' => $partNumber,
            ]);

            $request = $this->s3Client->createPresignedRequest($command, "+{$expiresIn} seconds");
            return (string) $request->getUri();
        } catch (AwsException $e) {
            throw new \Exception("Failed to generate multipart upload URL: " . $e->getMessage());
        }
    }

    /**
     * Generate pre-signed URLs for multiple parts at once
     *
     * @param string $key The S3 object key
     * @param string $uploadId The multipart upload ID
     * @param int $totalParts Total number of parts
     * @param int $expiresIn Expiration time in seconds
     * @return array Array of ['part_number' => int, 'url' => string]
     */
    public function generateMultipartUploadUrls(
        string $key,
        string $uploadId,
        int $totalParts,
        int $expiresIn = 3600
    ): array {
        $urls = [];

        for ($i = 1; $i <= $totalParts; $i++) {
            $urls[] = [
                'part_number' => $i,
                'url' => $this->generateMultipartUploadUrl($key, $uploadId, $i, $expiresIn),
            ];
        }

        return $urls;
    }

    /**
     * Complete a multipart upload
     *
     * @param string $key The S3 object key
     * @param string $uploadId The multipart upload ID
     * @param array $parts Array of ['PartNumber' => int, 'ETag' => string]
     * @return array ['location' => string, 'key' => string]
     */
    public function completeMultipartUpload(string $key, string $uploadId, array $parts): array
    {
        try {
            $result = $this->s3Client->completeMultipartUpload([
                'Bucket' => $this->bucket,
                'Key' => $key,
                'UploadId' => $uploadId,
                'MultipartUpload' => [
                    'Parts' => $parts,
                ],
            ]);

            return [
                'location' => $result['Location'],
                'key' => $key,
                'bucket' => $this->bucket,
            ];
        } catch (AwsException $e) {
            throw new \Exception("Failed to complete multipart upload: " . $e->getMessage());
        }
    }

    /**
     * Abort a multipart upload (cleanup)
     *
     * @param string $key The S3 object key
     * @param string $uploadId The multipart upload ID
     * @return void
     */
    public function abortMultipartUpload(string $key, string $uploadId): void
    {
        try {
            $this->s3Client->abortMultipartUpload([
                'Bucket' => $this->bucket,
                'Key' => $key,
                'UploadId' => $uploadId,
            ]);
        } catch (AwsException $e) {
            throw new \Exception("Failed to abort multipart upload: " . $e->getMessage());
        }
    }

    /**
     * Generate a unique S3 key for a file
     *
     * @param string $directory The directory prefix
     * @param string $filename The original filename
     * @param bool $preserveExtension Whether to preserve the file extension
     * @return string The generated S3 key
     */
    public function generateUniqueKey(
        string $directory,
        string $filename,
        bool $preserveExtension = true
    ): string {
        $extension = $preserveExtension ? '.' . pathinfo($filename, PATHINFO_EXTENSION) : '';
        $uniqueName = Str::random(40) . $extension;

        return trim($directory, '/') . '/' . $uniqueName;
    }

    /**
     * Verify that a file exists in S3 with retry logic
     *
     * @param string $key The S3 object key
     * @param int $maxRetries Maximum number of retries
     * @param int $delayMs Delay between retries in milliseconds
     * @return bool
     */
    public function fileExists(string $key, int $maxRetries = 3, int $delayMs = 500): bool
    {
        $lastException = null;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $exists = $this->s3Client->doesObjectExist($this->bucket, $key);
                if ($exists) {
                    Log::info("File verified in S3", [
                        'key' => $key,
                        'bucket' => $this->bucket,
                        'attempt' => $attempt,
                    ]);
                    return true;
                }

                // File not found, but operation succeeded - return false immediately
                if ($attempt === 1) {
                    Log::warning("File not found in S3", [
                        'key' => $key,
                        'bucket' => $this->bucket,
                    ]);
                }
                return false;
            } catch (AwsException $e) {
                $lastException = $e;
                Log::warning("Attempt $attempt to verify file in S3 failed", [
                    'key' => $key,
                    'bucket' => $this->bucket,
                    'error' => $e->getMessage(),
                    'attempt' => $attempt,
                    'max_retries' => $maxRetries,
                ]);

                // Don't retry on 404 errors
                if ($e->getStatusCode() === 404) {
                    return false;
                }

                // Sleep before retry (except on last attempt)
                if ($attempt < $maxRetries) {
                    usleep($delayMs * 1000);
                }
            }
        }

        Log::error("Failed to verify file in S3 after retries", [
            'key' => $key,
            'bucket' => $this->bucket,
            'error' => $lastException?->getMessage(),
            'attempts' => $maxRetries,
        ]);
        return false;
    }

    /**
     * Get file metadata from S3 with retry logic
     *
     * @param string $key The S3 object key
     * @param int $maxRetries Maximum number of retries
     * @param int $delayMs Delay between retries in milliseconds
     * @return array|null ['size' => int, 'content_type' => string, 'last_modified' => string]
     */
    public function getFileMetadata(string $key, int $maxRetries = 3, int $delayMs = 500): ?array
    {
        $lastException = null;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $result = $this->s3Client->headObject([
                    'Bucket' => $this->bucket,
                    'Key' => $key,
                ]);

                // Convert DateTimeResult to ISO8601 string
                $lastModified = $result['LastModified'];
                $lastModifiedString = $lastModified instanceof \DateTimeInterface
                    ? $lastModified->format('c')
                    : (string) $lastModified;

                $metadata = [
                    'size' => $result['ContentLength'],
                    'content_type' => $result['ContentType'],
                    'last_modified' => $lastModifiedString,
                    'etag' => trim($result['ETag'], '"'),
                ];

                Log::info("File metadata retrieved from S3", [
                    'key' => $key,
                    'bucket' => $this->bucket,
                    'attempt' => $attempt,
                ]);

                return $metadata;
            } catch (AwsException $e) {
                $lastException = $e;
                Log::warning("Attempt $attempt to get file metadata from S3 failed", [
                    'key' => $key,
                    'bucket' => $this->bucket,
                    'error' => $e->getMessage(),
                    'status_code' => $e->getStatusCode(),
                    'attempt' => $attempt,
                    'max_retries' => $maxRetries,
                ]);

                // Don't retry on 404 errors
                if ($e->getStatusCode() === 404) {
                    Log::error("File not found in S3 (404)", [
                        'key' => $key,
                        'bucket' => $this->bucket,
                    ]);
                    return null;
                }

                // Sleep before retry (except on last attempt)
                if ($attempt < $maxRetries) {
                    usleep($delayMs * 1000);
                }
            }
        }

        Log::error("Failed to get file metadata from S3 after retries", [
            'key' => $key,
            'bucket' => $this->bucket,
            'error' => $lastException?->getMessage(),
            'attempts' => $maxRetries,
        ]);
        return null;
    }

    /**
     * Generate a pre-signed URL for downloading a file from S3
     *
     * @param string $key The S3 object key
     * @param int $expiresIn Expiration time in seconds (default: 15 minutes)
     * @param string|null $filename Optional original filename for Content-Disposition header
     * @return string The pre-signed download URL
     */
    public function generatePresignedDownloadUrl(string $key, int $expiresIn = 900, ?string $filename = null): string
    {
        try {
            $params = [
                'Bucket' => $this->bucket,
                'Key' => $key,
            ];

            // Add Content-Disposition header if filename is provided
            if ($filename) {
                $params['ResponseContentDisposition'] = 'attachment; filename="' . addslashes($filename) . '"';
            }

            $command = $this->s3Client->getCommand('GetObject', $params);

            $request = $this->s3Client->createPresignedRequest($command, "+{$expiresIn} seconds");
            return (string) $request->getUri();
        } catch (AwsException $e) {
            throw new \Exception("Failed to generate pre-signed download URL: " . $e->getMessage());
        }
    }

    /**
     * Update cache headers on an existing S3 object
     *
     * @param string $key The S3 object key
     * @param int $maxAge Cache max-age in seconds (default: 30 days)
     * @return bool
     */
    public function updateCacheHeaders(string $key, int $maxAge = 518400): bool
    {
        try {
            // Copy object to itself with new metadata (this updates the headers)
            $this->s3Client->copyObject([
                'Bucket' => $this->bucket,
                'CopySource' => $this->bucket . '/' . $key,
                'Key' => $key,
                'CacheControl' => "public, max-age={$maxAge}, immutable",
                'MetadataDirective' => 'REPLACE',
            ]);

            Log::info("Updated cache headers for S3 object", [
                'key' => $key,
                'max_age' => $maxAge,
            ]);

            return true;
        } catch (AwsException $e) {
            Log::error("Failed to update cache headers: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete a file from S3
     *
     * @param string $key The S3 object key
     * @return bool
     */
    public function deleteFile(string $key): bool
    {
        try {
            $this->s3Client->deleteObject([
                'Bucket' => $this->bucket,
                'Key' => $key,
            ]);
            return true;
        } catch (AwsException $e) {
            Log::error("Failed to delete S3 file: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Upload a file directly to S3 from the server (server-side upload)
     *
     * @param \Illuminate\Http\UploadedFile|resource|string $fileSource File to upload (UploadedFile, file path, or resource)
     * @param string $key The S3 object key (path)
     * @param array $options Additional options ['ContentType', 'Metadata', 'ACL', etc.]
     * @return string The S3 key of the uploaded file
     * @throws \Exception
     */
    public function uploadFile($fileSource, string $key, array $options = []): string
    {
        try {
            // Use Laravel's Storage facade with the idrivee2 disk
            // This leverages Laravel's built-in S3 driver configuration
            $disk = Storage::disk('idrivee2');

            if ($fileSource instanceof \Illuminate\Http\UploadedFile) {
                // Laravel UploadedFile
                $result = $disk->putFileAs(
                    dirname($key),
                    $fileSource,
                    basename($key),
                    $options
                );
            } elseif (is_string($fileSource) && file_exists($fileSource)) {
                // File path - use put with file contents
                $fileContents = file_get_contents($fileSource);
                $result = $disk->put($key, $fileContents, $options);
            } elseif (is_resource($fileSource)) {
                // File resource - use put with stream
                $fileContents = stream_get_contents($fileSource);
                $result = $disk->put($key, $fileContents, $options);
            } else {
                throw new \Exception("Invalid file source provided");
            }

            if (!$result) {
                throw new \Exception("Storage disk returned false");
            }

            Log::info("File uploaded to S3 via Laravel Storage", [
                'key' => $key,
                'bucket' => $this->bucket,
                'disk' => 'idrivee2',
            ]);

            return $key;
        } catch (\Exception $e) {
            Log::error("Failed to upload file to S3", [
                'key' => $key,
                'error' => $e->getMessage(),
                'type' => get_class($e),
            ]);
            throw new \Exception("Failed to upload file to S3: " . $e->getMessage());
        }
    }
}
