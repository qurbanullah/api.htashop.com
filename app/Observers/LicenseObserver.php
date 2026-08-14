<?php

declare(strict_types=1);

/**
 * License Observer class file.
 * php version 8.4
 *
 * @category  App\Observers
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

namespace App\Observers;

use App\Models\License;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

/**
 * License Observer class for handling automatic file content extraction.
 *
 * @category App\Observers
 *
 * @author   Qurban Ullah <qurbanullah@gmail.com>
 * @license  CC BY-NC-ND 4.0 Deed https://creativecommons.org/licenses/by-nc-nd/4.0/
 *
 * @link     https://github.com/qurbanullah
 */
class LicenseObserver
{
    /**
     * Handle the License "saved" event (fires after both create and update).
     * Reads and stores file contents whenever license_file or data_file are present.
     *
     * @param  \App\Models\License  $license
     * @return void
     */
    public function saved(License $license): void
    {
        $this->syncFileContents($license);
    }

    /**
     * Handle the License "updated" event.
     * Ensure file syncing runs on explicit updates as well.
     *
     * @param  \App\Models\License  $license
     * @return void
     */
    public function updated(License $license): void
    {
        $this->syncFileContents($license);
    }

    /**
     * Sync file contents from storage to database.
     * Reads the license file and data file contents and stores them in database columns.
     * For data_file: decodes base64 and parses as JSON key-value pairs.
     *
     * @param  \App\Models\License  $license
     * @return void
     */
    private function syncFileContents(License $license): void
    {
        try {
            $shouldUpdate = false;
            $updateData = [];

            // Check license_file: prefer idrivee2 S3 key, fallback to local private disk
            $licenseFilePath = $license->license_file;
            $licenseS3Key = $license->license_file_s3_key ?? null;

            if ($licenseS3Key && Storage::disk('idrivee2')->exists($licenseS3Key)) {
                $content = Storage::disk('idrivee2')->get($licenseS3Key);
                if ($content && $license->license_key !== $content) {
                    $updateData['license_key'] = $content;
                    $shouldUpdate = true;
                    Log::info("License Observer: Read license file content from idrivee2 for license ID {$license->id}");
                }
            } elseif ($licenseFilePath && Storage::disk('private')->exists($licenseFilePath)) {
                $content = Storage::disk('private')->get($licenseFilePath);
                if ($content && $license->license_key !== $content) {
                    $updateData['license_key'] = $content;
                    $shouldUpdate = true;
                    Log::info("License Observer: Read license file content from private disk for license ID {$license->id}");
                }
            }

            // Check data_file: prefer idrivee2 S3 key, fallback to local private disk
            $dataFilePath = $license->data_file;
            $dataS3Key = $license->data_file_s3_key ?? null;

            if ($dataS3Key && Storage::disk('idrivee2')->exists($dataS3Key)) {
                $content = Storage::disk('idrivee2')->get($dataS3Key);
            } elseif ($dataFilePath && Storage::disk('private')->exists($dataFilePath)) {
                $content = Storage::disk('private')->get($dataFilePath);
            } else {
                $content = null;
            }

            if ($content && $license->hardware_id !== $content) {
                // Store the base64 encoded content in hardware_id
                $updateData['hardware_id'] = $content;

                // Decode base64 and try to parse as JSON
                $decodedContent = base64_decode($content, true);

                if ($decodedContent !== false) {
                    // Try to parse as JSON
                    $jsonData = json_decode($decodedContent, true);

                    if (json_last_error() === JSON_ERROR_NONE && is_array($jsonData)) {
                        // Successfully decoded and parsed as JSON
                        $updateData['decoded_data'] = $jsonData;
                        Log::info("License Observer: Decoded base64 data file content as JSON for license ID {$license->id}");
                    } else {
                        // Not JSON, try to parse as key=value pairs
                        $parsedData = $this->parseKeyValuePairs($decodedContent);
                        if (!empty($parsedData)) {
                            $updateData['decoded_data'] = $parsedData;
                            Log::info("License Observer: Decoded base64 data file content as key-value pairs for license ID {$license->id}");
                        } else {
                            // Store as raw decoded text
                            $updateData['decoded_data'] = ['raw_content' => $decodedContent];
                            Log::info("License Observer: Decoded base64 data file content as raw text for license ID {$license->id}");
                        }
                    }
                } else {
                    Log::warning("License Observer: Failed to decode base64 data for license ID {$license->id}");
                }

                $shouldUpdate = true;
            }

            // Update the license with file contents if anything was read
            // Use updateQuietly to prevent triggering the updated event again (avoid infinite loop)
            if ($shouldUpdate) {
                $license->updateQuietly($updateData);
            }
        } catch (\Exception $e) {
            Log::error("License Observer: Failed to sync file contents for license ID {$license->id}: " . $e->getMessage());
        }
    }

    /**
     * Parse key=value pairs from text content.
     *
     * @param  string  $content
     * @return array
     */
    private function parseKeyValuePairs(string $content): array
    {
        $data = [];
        $lines = explode("\n", $content);

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $data[trim($key)] = trim($value);
        }

        return $data;
    }
}
