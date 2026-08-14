<?php

namespace App\Policies;

use App\Models\License;
use App\Models\User;

class LicensePolicy
{
    /**
     * Determine whether the user can view any licenses.
     */
    public function viewAny(User $user): bool
    {
        // Allow users with specific roles to view licenses
        if ($user->hasRole(['super-admin', 'admin', 'client', 'guest'])) {
            return true;
        }

        // Fallback to permission check
        return $user->can('view licenses');
    }

    /**
     * Determine whether the user can view the license.
     */
    public function view(User $user, License $license): bool
    {
        // Super admin and admin can view all licenses
        if ($user->hasRole(['super-admin', 'admin'])) {
            return true;
        }

        // Users can view their own licenses regardless of role
        if ($user->id === $license->user_id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can create licenses.
     */
    public function create(User $user): bool
    {
        // All authenticated users can create licenses
        return true;
    }

    /**
     * Determine whether the user can update the license.
     */
    public function update(User $user, License $license): bool
    {
        // Super admin and admin can update any license
        if ($user->hasRole(['super-admin', 'admin'])) {
            return true;
        }

        // Users can update their own pending licenses only
        if ($user->id === $license->user_id && $license->status === 'pending') {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can upload data files (for their own pending licenses).
     */
    public function uploadDataFile(User $user, License $license): bool
    {
        // Super admin and admin can upload data files for any license
        if ($user->hasRole(['super-admin', 'admin'])) {
            return true;
        }

        // Users can upload data files for their own pending licenses
        return $user->id === $license->user_id && $license->status === 'pending';
    }

    /**
     * Determine whether the user can download data files.
     */
    public function downloadDataFile(User $user, License $license): bool
    {
        // Super admin and admin can download any data file
        if ($user->hasRole(['super-admin', 'admin'])) {
            return true;
        }

        // Users can download their own license data files
        return $user->id === $license->user_id;
    }

    /**
     * Determine whether the user can download license files.
     */
    public function downloadLicenseFile(User $user, License $license): bool
    {
        // Super admin and admin can download any license file
        if ($user->hasRole(['super-admin', 'admin'])) {
            return true;
        }

        // Users can download their own license files
        return $user->id === $license->user_id;
    }

    /**
     * Determine whether the user can upload license files.
     */
    public function uploadLicenseFile(User $user): bool
    {
        // Only super-admin and admin can upload license files
        return $user->hasRole(['super-admin', 'admin']);
    }

    /**
     * Determine whether the user can change license status.
     */
    public function changeStatus(User $user): bool
    {
        // Only super-admin and admin can change license status
        return $user->hasRole(['super-admin', 'admin']);
    }

    /**
     * Determine whether the user can toggle license active/inactive status.
     */
    public function toggleActiveStatus(User $user): bool
    {
        // Only super-admin and admin can toggle active/inactive status
        return $user->hasRole(['super-admin', 'admin']);
    }

    /**
     * Determine whether the user can delete the license.
     */
    public function delete(User $user, License $license): bool
    {
        // Super admin can delete any license
        if ($user->hasRole(['super-admin'])) {
            return true;
        }

        // Admin can delete licenses with restrictions
        if ($user->hasRole(['admin'])) {
            return $user->can('delete licenses');
        }

        return false;
    }

    /**
     * Determine whether the user can restore the license.
     */
    public function restore(User $user, License $license): bool
    {
        // Only super admin can restore licenses
        return $user->hasRole(['super-admin']);
    }

    /**
     * Determine whether the user can permanently delete the license.
     */
    public function forceDelete(User $user, License $license): bool
    {
        // Only super admin can permanently delete licenses
        return $user->hasRole(['super-admin']);
    }
}
