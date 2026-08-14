<?php

namespace App\Policies;

use App\Models\Profile;
use App\Models\User;

class ProfilePolicy
{
    /**
     * Determine if the user can view the profile.
     */
    public function view(User $user, Profile $profile): bool
    {
        // Users can view public profiles
        if ($profile->is_public && $profile->is_active) {
            return true;
        }

        // Users can view their own profile
        return $this->owns($user, $profile);
    }

    /**
     * Determine if the user can view any profiles.
     */
    public function viewAny(User $user): bool
    {
        // All authenticated users can view the profiles list (filtered by public visibility)
        return true;
    }

    /**
     * Determine if the user can create a profile.
     */
    public function create(User $user): bool
    {
        // Users can create a profile if they don't already have one
        $existingProfile = Profile::where('profilable_type', User::class)
            ->where('profilable_id', $user->id)
            ->first();

        return !$existingProfile;
    }

    /**
     * Determine if the user can update the profile.
     */
    public function update(User $user, Profile $profile): bool
    {
        // Users can update their own profile
        if ($this->owns($user, $profile)) {
            return true;
        }

        // Admins and editors can update any profile
        return $this->isAdmin($user);
    }

    /**
     * Determine if the user can delete the profile.
     */
    public function delete(User $user, Profile $profile): bool
    {
        // Users can delete their own profile
        if ($this->owns($user, $profile)) {
            return true;
        }

        // Only admins can delete other users' profiles
        return $this->isAdmin($user);
    }

    /**
     * Determine if the user can verify a profile.
     */
    public function verify(User $user, Profile $profile): bool
    {
        // Only admins and editors can verify profiles
        return $this->isAdmin($user) || $this->isEditor($user);
    }

    /**
     * Determine if the user can manage reviewer availability.
     */
    public function manageReviewerAvailability(User $user, Profile $profile): bool
    {
        // Users can manage their own reviewer settings
        if ($this->owns($user, $profile)) {
            return true;
        }

        // Editors and admins can manage any reviewer availability
        return $this->isAdmin($user) || $this->isEditor($user);
    }

    /**
     * Check if the user owns the profile.
     */
    private function owns(User $user, Profile $profile): bool
    {
        // Check if this profile belongs to the user
        return $profile->profilable_type === User::class
            && $profile->profilable_id === $user->id;
    }

    /**
     * Check if the user is an admin.
     */
    private function isAdmin(User $user): bool
    {
        return $user->roles()
            ->whereIn('name', ['admin', 'super_admin', 'administrator'])
            ->exists();
    }

    /**
     * Check if the user is an editor.
     */
    private function isEditor(User $user): bool
    {
        return $user->roles()
            ->whereIn('name', ['editor', 'editor_in_chief', 'editor-in-chief', 'eic'])
            ->exists();
    }
}
