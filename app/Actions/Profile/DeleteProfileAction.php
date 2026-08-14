<?php

namespace App\Actions\Profile;

use App\Models\Profile;
use Illuminate\Support\Facades\DB;

class DeleteProfileAction
{
    /**
     * Delete a profile.
     *
     * @param  Profile  $profile  The profile to delete
     * @param  bool  $hardDelete  Whether to permanently delete or soft delete
     * @return bool
     */
    public function execute(Profile $profile, bool $hardDelete = false): bool
    {
        return DB::transaction(function () use ($profile, $hardDelete) {
            if ($hardDelete) {
                // Permanently delete the profile
                return $profile->forceDelete();
            }

            // Soft delete the profile
            return $profile->delete();
        });
    }
}
