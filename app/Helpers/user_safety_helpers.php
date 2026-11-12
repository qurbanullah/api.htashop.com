<?php

use App\Models\DeletedUser;
use App\Models\User;

/**
 * Helper functions for safely handling user data, especially soft deleted users
 *
 * These functions provide safe ways to access user information without throwing
 * errors when users are soft deleted or null.
 */
if (! function_exists('safe_user_name')) {
    /**
     * Get safe user name with fallback
     * Handles soft deleted AND permanently deleted users
     *
     * @param  mixed  $user  User object, user ID, or null
     * @param  string  $fallback  Default text for deleted/missing users
     */
    function safe_user_name($user, string $fallback = '[Deleted User]'): string
    {
        // Handle null user
        if (is_null($user)) {
            return $fallback;
        }

        // Handle user ID (integer)
        if (is_numeric($user)) {
            $user = User::withTrashed()->find($user);

            // If user not found even with trashed, check permanently deleted users
            if (! $user) {
                $deletedUser = DeletedUser::where('original_user_id', $user)->first();
                if ($deletedUser) {
                    return $deletedUser->display_name.' (Permanently Deleted)';
                }

                return $fallback;
            }
        }

        // Handle user object
        if ($user instanceof User) {
            if ($user->trashed()) {
                return ($user->name ?? $fallback).' (Deleted)';
            }

            return $user->name ?? '[Unknown User]';
        }

        // Handle DeletedUser object
        if ($user instanceof DeletedUser) {
            return $user->display_name.' (Permanently Deleted)';
        }

        return $fallback;
    }
}

if (! function_exists('safe_user_email')) {
    /**
     * Get safe user email with fallback
     * Handles soft deleted AND permanently deleted users
     *
     * @param  mixed  $user  User object, user ID, or null
     * @param  string  $fallback  Default email for deleted/missing users
     */
    function safe_user_email($user, string $fallback = '[deleted-user@example.com]'): string
    {
        // Handle null user
        if (is_null($user)) {
            return $fallback;
        }

        // Handle user ID (integer)
        if (is_numeric($user)) {
            $user = User::withTrashed()->find($user);

            // If user not found even with trashed, check permanently deleted users
            if (! $user) {
                $deletedUser = DeletedUser::where('original_user_id', $user)->first();
                if ($deletedUser) {
                    return $deletedUser->safe_email;
                }

                return '[permanently-deleted@gdpr-compliance.local]';
            }
        }

        // Handle user object
        if ($user instanceof User) {
            return $user->email ?? $fallback;
        }

        // Handle DeletedUser object
        if ($user instanceof DeletedUser) {
            return $user->safe_email;
        }

        return $fallback;
    }
}

if (! function_exists('safe_user_display')) {
    /**
     * Get safe user display with name and status
     * Handles soft deleted AND permanently deleted users
     *
     * @param  mixed  $user  User object, user ID, or null
     * @param  bool  $showStatus  Whether to show (Deleted) status
     */
    function safe_user_display($user, bool $showStatus = true): array
    {
        $defaultReturn = [
            'name' => '[Deleted User]',
            'email' => '[deleted-user@example.com]',
            'is_deleted' => true,
            'is_permanently_deleted' => false,
            'display_name' => '[Deleted User]',
        ];

        // Handle null user
        if (is_null($user)) {
            return $defaultReturn;
        }

        // Handle user ID (integer)
        if (is_numeric($user)) {
            $userId = $user;
            $user = User::withTrashed()->find($userId);

            // If user not found even with trashed, check permanently deleted users
            if (! $user) {
                $deletedUser = DeletedUser::where('original_user_id', $userId)->first();
                if ($deletedUser) {
                    return [
                        'name' => $deletedUser->name ?? '[Permanently Deleted User]',
                        'email' => $deletedUser->safe_email,
                        'is_deleted' => true,
                        'is_permanently_deleted' => true,
                        'display_name' => $showStatus ? $deletedUser->display_name.' (Permanently Deleted)' : $deletedUser->display_name,
                        'anonymized_identifier' => $deletedUser->anonymized_identifier,
                        'deletion_reason' => $deletedUser->deletion_reason,
                    ];
                }

                return array_merge($defaultReturn, ['is_permanently_deleted' => true]);
            }
        }

        // Handle user object
        if ($user instanceof User) {
            $isDeleted = $user->trashed();
            $name = $user->name ?? '[Unknown User]';
            $email = $user->email ?? '[deleted-user@example.com]';

            $displayName = $name;
            if ($isDeleted && $showStatus) {
                $displayName .= ' (Deleted)';
            }

            return [
                'name' => $name,
                'email' => $email,
                'is_deleted' => $isDeleted,
                'is_permanently_deleted' => false,
                'display_name' => $displayName,
            ];
        }

        // Handle DeletedUser object
        if ($user instanceof DeletedUser) {
            return [
                'name' => $user->name ?? '[Permanently Deleted User]',
                'email' => $user->safe_email,
                'is_deleted' => true,
                'is_permanently_deleted' => true,
                'display_name' => $showStatus ? $user->display_name.' (Permanently Deleted)' : $user->display_name,
                'anonymized_identifier' => $user->anonymized_identifier,
                'deletion_reason' => $user->deletion_reason,
            ];
        }

        return $defaultReturn;
    }
}

if (! function_exists('get_user_with_trashed')) {
    /**
     * Get user including soft deleted users
     */
    function get_user_with_trashed(int $userId): ?User
    {
        return User::withTrashed()->find($userId);
    }
}

if (! function_exists('get_user_or_deleted_record')) {
    /**
     * Get user including soft deleted users OR permanently deleted record
     */
    function get_user_or_deleted_record(int $userId): User|DeletedUser|null
    {
        // First try to find the user (including soft deleted)
        $user = User::withTrashed()->find($userId);

        if ($user) {
            return $user;
        }

        // If not found, check permanently deleted users
        return DeletedUser::where('original_user_id', $userId)->first();
    }
}

if (! function_exists('permanently_delete_user')) {
    /**
     * Permanently delete a user while preserving minimal information for GDPR compliance
     *
     * @param  bool  $anonymize  Whether to anonymize the data
     * @param  User|null  $deletedBy  Who is deleting the user
     */
    function permanently_delete_user(User $user, array $deletionReason = [], bool $anonymize = true, ?User $deletedBy = null): DeletedUser
    {
        // Create deleted user record before removing the original
        if ($anonymize) {
            $deletedUser = DeletedUser::createAnonymized($user->id, $deletionReason, $deletedBy);
        } else {
            $deletedUser = DeletedUser::createFromUser($user, $deletionReason, $deletedBy);
        }

        // Force delete the user (permanent)
        $user->forceDelete();

        return $deletedUser;
    }
}

if (! function_exists('soft_delete_user_with_audit')) {
    /**
     * Soft delete a user with audit trail
     *
     * @param  User  $user  The user to soft delete
     * @param  User|null  $deletedBy  The user performing the deletion
     * @param  string  $deletedByType  The type of entity doing the deletion (user, admin, superadmin, system)
     */
    function soft_delete_user_with_audit(User $user, ?User $deletedBy = null, string $deletedByType = 'user'): bool
    {
        return $user->deleteWithAudit($deletedBy, $deletedByType);
    }
}

if (! function_exists('restore_user_with_audit')) {
    /**
     * Restore a soft deleted user with audit trail
     *
     * @param  User  $user  The user to restore
     * @param  User|null  $restoredBy  The user performing the restoration
     * @param  string  $restoredByType  The type of entity doing the restoration (user, admin, superadmin, system)
     */
    function restore_user_with_audit(User $user, ?User $restoredBy = null, string $restoredByType = 'user'): bool
    {
        return $user->restoreWithAudit($restoredBy, $restoredByType);
    }
}

if (! function_exists('is_user_permanently_deleted')) {
    /**
     * Check if a user has been permanently deleted
     */
    function is_user_permanently_deleted(int $userId): bool
    {
        return DeletedUser::where('original_user_id', $userId)->exists();
    }
}
