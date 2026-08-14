<?php

namespace App\Policies;

use App\Models\Eula;
use App\Models\User;

class EulaPolicy
{
    /**
     * Determine whether the user can view any EULAs.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isStaff();
    }

    /**
     * Determine whether the user can view the EULA.
     */
    public function view(User $user, Eula $eula): bool
    {
        return $user->isAdmin() || $user->isStaff();
    }

    /**
     * Determine whether the user can create EULAs.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can update the EULA.
     */
    public function update(User $user, Eula $eula): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can delete the EULA.
     */
    public function delete(User $user, Eula $eula): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can restore the EULA.
     */
    public function restore(User $user, Eula $eula): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can permanently delete the EULA.
     */
    public function forceDelete(User $user, Eula $eula): bool
    {
        return $user->isAdmin();
    }
}
