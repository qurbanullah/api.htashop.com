<?php

declare(strict_types=1);

/**
 * User Online Status Helper
 * php version 8.4
 *
 * @category  App\Helpers
 *
 * @author    Qurban Ullah <qurbanullah@gmail.com>
 * @copyright 2024 Qurban Ullah - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by Qurban Ullah <qurbanullah@gmail.com>, 2024
 *
 * @license   CC BY-NC-ND 4.0 Deed https://creativecommons.org/licenses/by-nc-nd/4.0/
 *
 * @version   GIT: <git_id>
 *
 * @link      https://github.com/qurbanullah
 */

namespace App\Helpers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UserOnlineHelper
{
    private static array $onlineUsers = [];
    private static bool $loaded = false;

    /**
     * Check if a user is online
     *
     * @param int $userId
     * @return bool
     */
    public static function isUserOnline(int $userId): bool
    {
        self::loadOnlineUsers();

        return isset(self::$onlineUsers[$userId]);
    }

    /**
     * Get all online user IDs
     *
     * @return array
     */
    public static function getOnlineUserIds(): array
    {
        self::loadOnlineUsers();

        return array_keys(self::$onlineUsers);
    }

    /**
     * Get count of online users
     *
     * @return int
     */
    public static function getOnlineUsersCount(): int
    {
        return count(self::getOnlineUserIds());
    }

    /**
     * Check if multiple users are online
     *
     * @param array $userIds
     * @return array [userId => isOnline]
     */
    public static function areUsersOnline(array $userIds): array
    {
        self::loadOnlineUsers();

        $result = [];
        foreach ($userIds as $userId) {
            $result[$userId] = isset(self::$onlineUsers[$userId]);
        }

        return $result;
    }

    /**
     * Reset the cached online users data
     * Useful for testing or when you need fresh data
     *
     * @return void
     */
    public static function reset(): void
    {
        self::$onlineUsers = [];
        self::$loaded = false;
    }

    /**
     * Load all online users from cache in a single query
     *
     * @return void
     */
    private static function loadOnlineUsers(): void
    {
        if (self::$loaded) {
            return;
        }

        self::$onlineUsers = [];

        try {
            if (Cache::getStore() instanceof \Illuminate\Cache\RedisStore) {
                // For Redis cache driver
                $onlineKeys = Cache::getRedis()->keys('*user-is-online-*');
                foreach ($onlineKeys as $key) {
                    // Extract user ID from key like "real3dtech_cache_user-is-online-101"
                    if (preg_match('/user-is-online-(\d+)/', $key, $matches)) {
                        self::$onlineUsers[(int)$matches[1]] = true;
                    }
                }
            } else {
                // For database cache driver
                $cacheEntries = DB::table('cache')
                    ->where('key', 'like', '%user-is-online-%')
                    ->pluck('key');

                foreach ($cacheEntries as $key) {
                    // Extract user ID from key like "real3dtech_cache_user-is-online-101"
                    if (preg_match('/user-is-online-(\d+)/', $key, $matches)) {
                        self::$onlineUsers[(int)$matches[1]] = true;
                    }
                }
            }
        } catch (\Exception $e) {
            // Log the error but don't throw - fallback to empty array
            Log::warning('Failed to load online users: ' . $e->getMessage());
            self::$onlineUsers = [];
        }

        self::$loaded = true;
    }

    /**
     * Get online users count efficiently using database query
     * This is useful for statistics where you only need the count
     *
     * @return int
     */
    public static function getOnlineUsersCountDirect(): int
    {
        try {
            if (Cache::getStore() instanceof \Illuminate\Cache\RedisStore) {
                $onlineKeys = Cache::getRedis()->keys('*user-is-online-*');
                return count($onlineKeys);
            } else {
                return DB::table('cache')
                    ->where('key', 'like', '%user-is-online-%')
                    ->count();
            }
        } catch (\Exception $e) {
            Log::warning('Failed to get online users count: ' . $e->getMessage());
            return 0;
        }
    }
}
