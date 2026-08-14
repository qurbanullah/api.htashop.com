<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Helper class for managing admin user operations with caching
 */
class AdminHelper
{
    /**
     * Cache duration in seconds (15 minutes)
     */
    private const CACHE_DURATION = 900;

    /**
     * Cache key prefix
     */
    private const CACHE_PREFIX = 'admins';

    /**
     * Get all admin and super-admin users with caching
     *
     * @return Collection
     */
    public static function getAdmins(): Collection
    {
        $cacheKey = self::CACHE_PREFIX . ':all';

        return Cache::remember($cacheKey, self::CACHE_DURATION, function () {
            return User::role(['super-admin', 'admin'])
                ->whereNotNull('email')
                ->where('email', '!=', '')
                ->get()
                ->unique('email');
        });
    }

    /**
     * Get admins excluding a specific user
     *
     * @param int $excludeUserId User ID to exclude
     * @return Collection
     */
    public static function getAdminsExcludingUser(int $excludeUserId): Collection
    {
        return self::getAdmins()->filter(function ($admin) use ($excludeUserId) {
            return $admin->id !== $excludeUserId;
        })->values();
    }

    /**
     * Get admins excluding a specific email address
     *
     * @param string|null $excludeEmail Email address to exclude
     * @return Collection
     */
    public static function getAdminsExcludingEmail(?string $excludeEmail): Collection
    {
        if (empty($excludeEmail)) {
            return self::getAdmins();
        }

        return self::getAdmins()->filter(function ($admin) use ($excludeEmail) {
            return strtolower($admin->email) !== strtolower($excludeEmail);
        })->values();
    }

    /**
     * Get admin emails only (for quick lookups)
     *
     * @return Collection
     */
    public static function getAdminEmails(): Collection
    {
        $cacheKey = self::CACHE_PREFIX . ':emails';

        return Cache::remember($cacheKey, self::CACHE_DURATION, function () {
            return User::role(['super-admin', 'admin'])
                ->whereNotNull('email')
                ->where('email', '!=', '')
                ->pluck('email')
                ->unique()
                ->values();
        });
    }

    /**
     * Check if an email belongs to an admin
     *
     * @param string $email
     * @return bool
     */
    public static function isAdminEmail(string $email): bool
    {
        return self::getAdminEmails()->contains(function ($adminEmail) use ($email) {
            return strtolower($adminEmail) === strtolower($email);
        });
    }

    /**
     * Clear admin cache
     *
     * @return void
     */
    public static function clearCache(): void
    {
        $cacheHelper = new CacheHelper();
        $cacheHelper->clearPattern(self::CACHE_PREFIX . ':*');
    }

    /**
     * Refresh admin cache
     *
     * @return Collection
     */
    public static function refreshCache(): Collection
    {
        self::clearCache();
        return self::getAdmins();
    }

    /**
     * Get all support staff (support-assistant, manager, admin, super-admin) with caching
     *
     * @return Collection
     */
    public static function getSupportStaff(): Collection
    {
        $cacheKey = self::CACHE_PREFIX . ':support_staff';

        return Cache::remember($cacheKey, self::CACHE_DURATION, function () {
            return User::role(['super-admin', 'admin', 'manager', 'support-assistant'])
                ->get()
                ->unique('email');
        });
    }

    /**
     * Get support staff excluding a specific user
     *
     * @param int $excludeUserId User ID to exclude
     * @return Collection
     */
    public static function getSupportStaffExcludingUser(int $excludeUserId): Collection
    {
        return self::getSupportStaff()->filter(function ($user) use ($excludeUserId) {
            return $user->id !== $excludeUserId;
        })->values();
    }

    /**
     * Get support staff excluding a specific email address
     *
     * @param string|null $excludeEmail Email address to exclude
     * @return Collection
     */
    public static function getSupportStaffExcludingEmail(?string $excludeEmail): Collection
    {
        if (empty($excludeEmail)) {
            return self::getSupportStaff();
        }

        return self::getSupportStaff()->filter(function ($user) use ($excludeEmail) {
            return strtolower($user->email) !== strtolower($excludeEmail);
        })->values();
    }

    /**
     * Get all managers (manager, admin, super-admin) with caching
     *
     * @return Collection
     */
    public static function getManagers(): Collection
    {
        $cacheKey = self::CACHE_PREFIX . ':managers';

        return Cache::remember($cacheKey, self::CACHE_DURATION, function () {
            return User::role(['super-admin', 'admin', 'manager'])
                ->get()
                ->unique('email');
        });
    }

    /**
     * Get managers excluding a specific user
     *
     * @param int $excludeUserId User ID to exclude
     * @return Collection
     */
    public static function getManagersExcludingUser(int $excludeUserId): Collection
    {
        return self::getManagers()->filter(function ($user) use ($excludeUserId) {
            return $user->id !== $excludeUserId;
        })->values();
    }

    /**
     * Get managers excluding a specific email address
     *
     * @param string|null $excludeEmail Email address to exclude
     * @return Collection
     */
    public static function getManagersExcludingEmail(?string $excludeEmail): Collection
    {
        if (empty($excludeEmail)) {
            return self::getManagers();
        }

        return self::getManagers()->filter(function ($user) use ($excludeEmail) {
            return strtolower($user->email) !== strtolower($excludeEmail);
        })->values();
    }

    /**
     * Get users by specific roles with caching
     *
     * @param array $roles Array of role names
     * @param int|null $excludeUserId User ID to exclude (optional)
     * @param string|null $excludeEmail Email to exclude (optional)
     * @return Collection
     */
    public static function getUsersByRoles(array $roles, ?int $excludeUserId = null, ?string $excludeEmail = null): Collection
    {
        $cacheKey = self::CACHE_PREFIX . ':roles:' . md5(implode(',', $roles));

        $users = Cache::remember($cacheKey, self::CACHE_DURATION, function () use ($roles) {
            return User::role($roles)
                ->get()
                ->unique('email');
        });

        // Apply filters if provided
        if ($excludeUserId !== null) {
            $users = $users->filter(function ($user) use ($excludeUserId) {
                return $user->id !== $excludeUserId;
            });
        }

        if (!empty($excludeEmail)) {
            $users = $users->filter(function ($user) use ($excludeEmail) {
                return strtolower($user->email) !== strtolower($excludeEmail);
            });
        }

        return $users->values();
    }
}
