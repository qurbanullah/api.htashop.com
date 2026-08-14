<?php

namespace App\Policies;

use App\Models\Tutorial;
use App\Models\User;

/**
 * Authorization policy for Tutorial model.
 *
 * This class follows SOLID principles:
 * - Single Responsibility: Only handles authorization for tutorials
 */
class TutorialPolicy
{
    /**
     * Determine whether the user can view any tutorials.
     */
    public function viewAny(User $user): bool
    {
        // Any authenticated user can view tutorials
        return true;
    }

    /**
     * Determine whether the user can view the tutorial.
     */
    public function view(User $user, Tutorial $tutorial): bool
    {
        // Users can view published tutorials or their own drafts
        return $tutorial->isPublished()
            || $tutorial->created_by === $user->id
            || $user->hasRole(['admin', 'super_admin']);
    }

    /**
     * Determine whether the user can create tutorials.
     */
    public function create(User $user): bool
    {
        // Only admins and super admins can create tutorials
        return $user->hasRole(['admin', 'super_admin']);
    }

    /**
     * Determine whether the user can update the tutorial.
     */
    public function update(User $user, Tutorial $tutorial): bool
    {
        // Admins, super admins, or the creator can update
        return $tutorial->created_by === $user->id
            || $user->hasRole(['admin', 'super_admin']);
    }

    /**
     * Determine whether the user can delete the tutorial.
     */
    public function delete(User $user, Tutorial $tutorial): bool
    {
        // Only admins and super admins can delete tutorials
        return $user->hasRole(['admin', 'super_admin']);
    }

    /**
     * Determine whether the user can publish the tutorial.
     */
    public function publish(User $user, Tutorial $tutorial): bool
    {
        // Only admins and super admins can publish tutorials
        return $user->hasRole(['admin', 'super_admin']);
    }
}
