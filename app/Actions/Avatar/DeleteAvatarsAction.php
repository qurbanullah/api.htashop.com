<?php

namespace App\Actions\Avatar;

use App\Models\Avatar;
use Illuminate\Support\Facades\Storage;

class DeleteAvatarsAction
{
    public function handle(int|string $modelId, string $modelType): void
    {
        $disk = Storage::disk('idrivee2');
        $avatars = Avatar::withTrashed()
            ->where('avatareable_id', $modelId)
            ->where('avatareable_type', $modelType)
            ->get();

        foreach ($avatars as $avatar) {
            if (!empty($avatar->path) && $disk->exists($avatar->path)) {
                $disk->delete($avatar->path);
            }
        }

        Avatar::withTrashed()
            ->where('avatareable_id', $modelId)
            ->where('avatareable_type', $modelType)
            ->forceDelete();
    }
}
