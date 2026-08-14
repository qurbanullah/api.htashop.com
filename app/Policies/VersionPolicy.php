<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Version;
use Illuminate\Auth\Access\HandlesAuthorization;

class VersionPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any versions.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['super-admin', 'admin', 'editor']);
    }

    /**
     * Determine whether the user can view the version.
     */
    public function view(User $user, Version $version): bool
    {
        return $user->hasAnyRole(['super-admin', 'admin', 'editor']);
    }

    /**
     * Determine whether the user can create versions.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole(['super-admin', 'admin']);
    }

    /**
     * Determine whether the user can update the version.
     */
    public function update(User $user, Version $version): bool
    {
        return $user->hasAnyRole(['super-admin', 'admin']);
    }

    /**
     * Determine whether the user can delete the version.
     */
    public function delete(User $user, Version $version): bool
    {
        // Only super-admin or admin may delete versions
        return $user->hasAnyRole(['super-admin', 'admin']);
    }
}
