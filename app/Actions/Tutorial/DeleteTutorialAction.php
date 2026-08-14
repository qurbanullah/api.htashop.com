<?php

declare(strict_types=1);

namespace App\Actions\Tutorial;

use App\Models\Tutorial;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * CRUD operation class for Tutorial Model - Delete operation.
 *
 * This class follows SOLID principles:
 * - Single Responsibility: Only handles deletion of tutorials
 */
class DeleteTutorialAction
{
    /**
     * Delete tutorial record from database.
     *
     * @param  Tutorial  $tutorial  tutorial instance to delete
     */
    public function execute(Tutorial $tutorial): bool
    {
        return DB::transaction(function () use ($tutorial) {
            // Delete associated files from storage
            if ($tutorial->thumbnail) {
                Storage::disk('idrivee2')->delete($tutorial->thumbnail);
            }

            if ($tutorial->video_file) {
                Storage::disk('idrivee2')->delete($tutorial->video_file);
            }

            // Detach relationships
            $tutorial->tags()->detach();
            $tutorial->categories()->detach();

            // Delete the tutorial
            return $tutorial->delete();
        });
    }
}
