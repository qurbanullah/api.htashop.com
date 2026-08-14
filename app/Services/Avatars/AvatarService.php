<?php

namespace App\Services\Avatars;

use App\Helpers\CacheHelper;
use App\Models\Avatar;
use App\Models\User;
use App\Actions\Avatar\CreateAvatarsFromUploadAction;
use App\Actions\Avatar\DeleteAvatarsAction;
use Illuminate\Support\Facades\Log;

/**
 * AvatarService
 *
 * Simplified avatar service - handles avatar operations with intelligent caching.
 * Ensures avatar data is cached to minimize database queries.
 * Automatically clears caches on avatar changes.
 */
class AvatarService
{
    private const AVATAR_CACHE_TTL = 86400; // 24 hours

    /**
     * Get user's avatar URL with caching
     *
     * @param User $user
     * @param string $type Avatar type: 'original', 'thumb', 'small', 'medium', 'large'
     * @return string|null
     */
    public function getUserAvatarUrl(User $user, string $type = 'medium'): ?string
    {
        $cacheKey = $this->getUserAvatarUrlCacheKey($user->id, $type);

        return CacheHelper::remember([], $cacheKey, self::AVATAR_CACHE_TTL, function () use ($user, $type) {
            return $user->getAvatarUrl($type);
        });
    }

    /**
     * Get all avatar URLs (all types) with caching
     *
     * @param User $user
     * @return array Format: ['thumb' => 'url', 'small' => 'url', ...]
     */
    public function getUserAvatarUrls(User $user): array
    {
        $cacheKey = $this->getUserAvatarUrlsCacheKey($user->id);

        return CacheHelper::remember([], $cacheKey, self::AVATAR_CACHE_TTL, function () use ($user) {
            return $user->getAvatarUrls();
        });
    }

    /**
     * Create avatars from S3 upload with variant paths
     *
     * @param User $user
     * @param array $variantPaths Format: ['thumb' => 's3/path', 'small' => 's3/path', ...]
     * @param string $filename Original filename
     * @param string $mimeType MIME type (default: image/jpeg)
     * @param array|null $metadata Additional metadata
     * @return bool
     */
    public function createAvatarFromUpload(
        User $user,
        array $variantPaths,
        string $filename,
        string $mimeType = 'image/jpeg',
        ?array $metadata = null,
    ): bool {
        // Create avatars (deletes old ones automatically)
        app(CreateAvatarsFromUploadAction::class)->handle(
            $user,
            $variantPaths,
            $filename,
            $mimeType,
            $metadata,
        );

        Log::info('Avatar created', [
                'user_id' => $user->id,
                'types' => array_keys($variantPaths),
            ]);

            // Clear all avatar caches for this user
            $this->clearUserAvatarCaches($user->id);

        return true;
    }

    /**
     * Delete user's avatar
     *
     * @param User $user
     * @return bool
     */
    public function deleteAvatar(User $user): bool
    {
        if ($user->avatars->isEmpty()) {
            return false;
        }

        try {
            // Delete from S3 and database
            app(DeleteAvatarsAction::class)->handle($user->id, get_class($user));

            Log::info('Avatar deleted', [
                'user_id' => $user->id,
            ]);

            // Clear caches
            $this->clearUserAvatarCaches($user->id);

            return true;
        } catch (\Exception $e) {
            Log::error('Avatar deletion failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Optimize avatar variants (generate WebP, re-compress, update metadata)
     * This is typically run as a background job after initial upload
     *
     * @param User $user
     * @return bool True if optimization was performed, false if skipped
     */
    public function optimizeAvatarIfNeeded(User $user): bool
    {
        if ($user->avatars->isEmpty()) {
            Log::warning('User has no avatars to optimize', [
                'user_id' => $user->id,
            ]);
            return false;
        }

        try {
            $disk = Storage::disk('idrivee2');
            $updated = false;

            Log::info('Starting avatar optimization', [
                'user_id' => $user->id,
                'avatar_count' => $user->avatars->count(),
            ]);

            // Optimize each avatar
            foreach ($user->avatars as $avatar) {
                if ($disk->exists($avatar->path)) {
                    // Update file metadata (size, dimensions)
                    $this->updateAvatarMetadata($avatar, $disk);
                    $updated = true;
                }
            }

            // Clear caches after optimization
            if ($updated) {
                $this->clearUserAvatarCaches($user->id);
            }

            Log::info('Avatar optimization completed', [
                'user_id' => $user->id,
                'updated' => $updated,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Avatar optimization failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Clear all caches for a user's avatar
     *
     * @param int $userId
     * @return void
     */
    public function clearUserAvatarCaches(int $userId): void
    {
        // Clear avatar URL caches for all types
        $types = ['original', 'thumb', 'small', 'medium', 'large'];
        foreach ($types as $type) {
            CacheHelper::forget([], $this->getUserAvatarUrlCacheKey($userId, $type));
        }

        // Clear all URLs cache
        CacheHelper::forget([], $this->getUserAvatarUrlsCacheKey($userId));
    }

    /**
     * Generate cache key for user avatar URL
     *
     * @param int $userId
     * @param string $type
     * @return string
     */
    private function getUserAvatarUrlCacheKey(int $userId, string $type): string
    {
        return "user:{$userId}:avatar-url:{$type}";
    }

    /**
     * Generate cache key for user avatar URLs (all types)
     *
     * @param int $userId
     * @return string
     */
    private function getUserAvatarUrlsCacheKey(int $userId): string
    {
        return "user:{$userId}:avatar-urls";
    }

    /**
     * Update avatar metadata (file size, dimensions)
     *
     * @param Avatar $avatar
     * @param \Illuminate\Contracts\Filesystem\Filesystem $disk
     * @return void
     */
    private function updateAvatarMetadata(Avatar $avatar, $disk): void
    {
        try {
            $updated = false;

            // Ensure file exists on disk
            if ($disk->exists($avatar->path)) {
                // Get file size
                $fileSize = $disk->size($avatar->path);
                if ($fileSize !== null && $avatar->file_size !== (int) $fileSize) {
                    $avatar->file_size = (int) $fileSize;
                    $updated = true;
                }

                // Try to read the file and get image dimensions
                try {
                    $contents = $disk->get($avatar->path);
                    if ($contents !== null && strlen($contents) > 0) {
                        $info = @getimagesizefromstring($contents);
                        if ($info !== false) {
                            $width = isset($info[0]) ? (int) $info[0] : null;
                            $height = isset($info[1]) ? (int) $info[1] : null;
                            if ($width !== null && $avatar->width !== $width) {
                                $avatar->width = $width;
                                $updated = true;
                            }
                            if ($height !== null && $avatar->height !== $height) {
                                $avatar->height = $height;
                                $updated = true;
                            }
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning('Failed to read avatar file for dimensions', [
                        'avatar_id' => $avatar->id,
                        'path' => $avatar->path,
                        'error' => $e->getMessage(),
                    ]);
                }

                if ($updated) {
                    $avatar->save();
                }

                Log::debug('Updated avatar metadata', [
                    'avatar_id' => $avatar->id,
                    'file_size' => $fileSize ?? null,
                    'width' => $avatar->width,
                    'height' => $avatar->height,
                ]);
            } else {
                Log::warning('Avatar file does not exist on disk', [
                    'avatar_id' => $avatar->id,
                    'path' => $avatar->path,
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to update avatar metadata', [
                'avatar_id' => $avatar->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
};
