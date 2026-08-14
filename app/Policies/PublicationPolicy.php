<?php

namespace App\Policies;

use App\Models\Publication;
use App\Models\User;

class PublicationPolicy
{
    /**
     * Determine whether the user can view any publications.
     */
    public function viewAny(User $user): bool
    {
        return true; // All authenticated users can view publications
    }

    /**
     * Determine whether the user can view the publication.
     */
    public function view(User $user, Publication $publication): bool
    {
        // Public publications can be viewed by anyone
        if ($publication->is_public) {
            return true;
        }

        // Owner can view their own publications
        if ($publication->publishable_type === User::class && $publication->publishable_id === $user->id) {
            return true;
        }

        // Admin and editors can view all publications
        return $user->hasAnyRole(['admin', 'editor']);
    }

    /**
     * Determine whether the user can create publications.
     */
    public function create(User $user): bool
    {
        // All authenticated users can create publications
        return true;
    }

    /**
     * Determine whether the user can update the publication.
     */
    public function update(User $user, Publication $publication): bool
    {
        // Admin and editors can update any publication
        if ($user->hasAnyRole(['admin', 'editor'])) {
            return true;
        }

        // Owner can update their own publications
        if ($publication->publishable_type === User::class && $publication->publishable_id === $user->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the publication.
     */
    public function delete(User $user, Publication $publication): bool
    {
        // Admin can delete any publication
        if ($user->hasRole('admin')) {
            return true;
        }

        // Owner can delete their own publications
        if ($publication->publishable_type === User::class && $publication->publishable_id === $user->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can restore the publication.
     */
    public function restore(User $user): bool
    {
        // Only admin can restore publications
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can permanently delete the publication.
     */
    public function forceDelete(User $user): bool
    {
        // Only admin can permanently delete publications
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can verify the publication.
     */
    public function verify(User $user, Publication $publication): bool
    {
        // Admin and editors can verify publications
        return $user->hasAnyRole(['admin', 'editor']);
    }
}
