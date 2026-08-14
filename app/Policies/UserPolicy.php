<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Support\Facades\Log;

class UserPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['super-admin', 'admin', 'editor']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, User $model): bool
    {
        // Super admin and admin can view any user
        if ($user->hasAnyRole(['super-admin', 'admin'])) {
            return true;
        }

        // Editor can view non-admin users
        if ($user->hasRole('editor')) {
            return !$model->hasAnyRole(['super-admin', 'admin']);
        }

        // Users can view their own profile
        return $user->id === $model->id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole(['super-admin', 'admin']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, User $model): bool
    {
        // Super admin can update anyone
        if ($user->hasRole('super-admin')) {
            return true;
        }

        // Admin can update non-super-admin users
        if ($user->hasRole('admin')) {
            return !$model->hasRole('super-admin');
        }

        // Users can update their own profile (limited fields)
        return $user->id === $model->id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, User $model): bool
    {
        // Allow users to delete their own account (soft delete), but prevent super-admin self-deletion
        if ($user->id === $model->id) {
            return !$user->hasRole('super-admin');
        }

        // Super admin can delete anyone except themselves
        if ($user->hasRole('super-admin')) {
            return true;
        }

        // Admin can delete non-super-admin users
        if ($user->hasRole('admin')) {
            return !$model->hasRole('super-admin');
        }

        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, User $model): bool
    {
        // Super admin can restore anyone
        if ($user->hasRole('super-admin')) {
            return true;
        }

        // Admin can restore non-super-admin users
        if ($user->hasRole('admin')) {
            return !$model->hasRole('super-admin');
        }

        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, User $model): bool
    {
        // Only super admin can force delete
        // And cannot force delete themselves
        return $user->hasRole('super-admin') && $user->id !== $model->id;
    }

    /**
     * Determine whether the user can assign roles to the model.
     */
    public function assignRoles(User $user, User $model): bool
    {
        Log::info('UserPolicy::assignRoles called', [
            'auth_user_id' => $user->id,
            'auth_user_roles_loaded' => $user->relationLoaded('roles'),
            'auth_user_roles' => $user->roles->pluck('name')->toArray(),
            'target_user_id' => $model->id,
            'target_user_roles_loaded' => $model->relationLoaded('roles'),
            'target_user_roles' => $model->roles->pluck('name')->toArray(),
        ]);

        // Cannot assign roles to yourself
        if ($user->id === $model->id) {
            Log::info('assignRoles: Denied - user trying to edit own roles');
            return false;
        }

        // Super admin can assign any roles
        if ($user->hasRole('super-admin')) {
            Log::info('assignRoles: Allowed - user is super-admin');
            return true;
        }

        // Admin can assign roles to non-super-admin users
        if ($user->hasRole('admin')) {
            $targetIsSuperAdmin = $model->hasRole('super-admin');
            Log::info('assignRoles: Admin check', [
                'target_is_super_admin' => $targetIsSuperAdmin,
                'result' => !$targetIsSuperAdmin
            ]);
            return !$targetIsSuperAdmin;
        }

        Log::info('assignRoles: Denied - user does not have required role');
        return false;
    }

    /**
     * Determine whether the user can verify email of the model.
     */
    public function verifyEmail(User $user, User $model): bool
    {
        return $user->hasAnyRole(['super-admin', 'admin']);
    }

    /**
     * Determine whether the user can view statistics.
     */
    public function viewStatistics(User $user): bool
    {
        return $user->hasAnyRole(['super-admin', 'admin', 'editor']);
    }
}
