<?php

namespace App\Traits\Profile;

use App\Models\Profile;
use Illuminate\Database\Eloquent\Relations\MorphOne;

trait HasProfile
{
    /**
     * Get the model's profile (polymorphic)
     */
    public function profile(): MorphOne
    {
        return $this->morphOne(Profile::class, 'profilable');
    }

    /**
     * Get the model's academic profile
     */
    public function academicProfile(): MorphOne
    {
        return $this->morphOne(Profile::class, 'profilable')
                    ->where('profile_type', Profile::TYPE_ACADEMIC);
    }

    /**
     * Create or update profile
     */
    public function updateOrCreateProfile(array $data, string $type = Profile::TYPE_ACADEMIC): Profile
    {
        return $this->profile()->updateOrCreate(
            ['profilable_id' => $this->id, 'profilable_type' => get_class($this)],
            array_merge($data, ['profile_type' => $type])
        );
    }

    /**
     * Check if model has a profile
     */
    public function hasProfile(): bool
    {
        return $this->profile()->exists();
    }

    /**
     * Check if model has an academic profile
     */
    public function hasAcademicProfile(): bool
    {
        return $this->academicProfile()->exists();
    }
}
