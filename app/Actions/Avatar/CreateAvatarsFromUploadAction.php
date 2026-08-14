<?php

namespace App\Actions\Avatar;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CreateAvatarsFromUploadAction
{
    public function handle(Model $model, array $variantPaths, string $filename, string $mimeType = 'image/jpeg', ?array $metadata = null): void
    {
        DB::transaction(function () use ($model, $variantPaths, $filename, $mimeType, $metadata) {
            app(DeleteAvatarsAction::class)->handle($model->getKey(), get_class($model));

            foreach ($variantPaths as $type => $path) {
                $model->avatars()->create([
                    'type' => $type,
                    'path' => $path,
                    'original_filename' => $filename,
                    'mime_type' => $mimeType,
                    'metadata' => $metadata,
                ]);
            }
        });

        Log::info('Avatars created', [
            'model_id' => $model->getKey(),
            'model_type' => get_class($model),
            'types' => array_keys($variantPaths),
        ]);
    }
}
