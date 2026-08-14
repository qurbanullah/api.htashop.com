<?php

/**
 * Global helper functions for user online status
 *
 * These functions can be used anywhere in the application without importing classes
 */

if (!function_exists('is_user_online')) {
    /**
     * Check if a user is online
     *
     * @param int|\App\Models\User|string $user User ID, User model, or string user ID
     * @return bool
     */
    function is_user_online($user): bool
    {
        // Handle different input types
        if ($user instanceof \App\Models\User) {
            $userId = $user->id;
        } elseif (is_string($user) || is_numeric($user)) {
            $userId = (int) $user;
        } elseif (is_int($user)) {
            $userId = $user;
        } else {
            return false; // Invalid input type
        }

        // Validate user ID
        if ($userId <= 0) {
            return false;
        }

        return \App\Helpers\UserOnlineHelper::isUserOnline($userId);
    }
}

if (!function_exists('get_online_users_count')) {
    /**
     * Get count of online users
     *
     * @return int
     */
    function get_online_users_count(): int
    {
        return \App\Helpers\UserOnlineHelper::getOnlineUsersCount();
    }
}

if (!function_exists('get_online_users_count_direct')) {
    /**
     * Get count of online users using direct database query
     * More efficient for statistics
     *
     * @return int
     */
    function get_online_users_count_direct(): int
    {
        return \App\Helpers\UserOnlineHelper::getOnlineUsersCountDirect();
    }
}

if (!function_exists('get_online_user_ids')) {
    /**
     * Get all online user IDs
     *
     * @return array
     */
    function get_online_user_ids(): array
    {
        return \App\Helpers\UserOnlineHelper::getOnlineUserIds();
    }
}

if (!function_exists('are_users_online')) {
    /**
     * Check if multiple users are online
     *
     * @param array $users Array of user IDs, User models, or mixed
     * @return array [userId => isOnline]
     */
    function are_users_online(array $users): array
    {
        // Convert all inputs to user IDs
        $userIds = [];
        foreach ($users as $user) {
            if ($user instanceof \App\Models\User) {
                $userIds[] = $user->id;
            } elseif (is_string($user) || is_numeric($user)) {
                $userId = (int) $user;
                if ($userId > 0) {
                    $userIds[] = $userId;
                }
            } elseif (is_int($user) && $user > 0) {
                $userIds[] = $user;
            }
        }

        return \App\Helpers\UserOnlineHelper::areUsersOnline($userIds);
    }
}
