<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class Avatar extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Business logic extracted to Actions:
     *   createFromUpload()  → App\Actions\Avatar\CreateAvatarsFromUploadAction
     *   deleteAllForAvatareable() → App\Actions\Avatar\DeleteAvatarsAction
     */

    protected $fillable = [
        'avatareable_id',
        'avatareable_type',
        'type',
        'path',
        'original_filename',
        'mime_type',
        'file_size',
        'width',
        'height',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'metadata' => 'json',
        'file_size' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the owning avatareable model (User, Team, Organization, etc.)
     */
    public function avatareable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the most recent avatar of a given type for an entity.
     */
    public static function getByType(int|string $modelId, string $modelType, string $type): ?self
    {
        return static::query()
            ->where('avatareable_id', $modelId)
            ->where('avatareable_type', $modelType)
            ->where('type', $type)
            ->latest('id')
            ->first();
    }

    /**
     * Get a map of type → path for all avatars of an entity.
     *
     * @return array<string, string>
     */
    public static function getAllPaths(int|string $modelId, string $modelType): array
    {
        return static::query()
            ->where('avatareable_id', $modelId)
            ->where('avatareable_type', $modelType)
            ->whereNotNull('path')
            ->pluck('path', 'type')
            ->all();
    }

    /**
     * Delete all avatars (database + storage) for an entity.
     */
    public static function deleteAllForAvatareable(int|string $modelId, string $modelType): bool
    {
        app(\App\Actions\Avatar\DeleteAvatarsAction::class)->handle($modelId, $modelType);

        return true;
    }

    /**
     * Create avatar records from uploaded variant paths (replaces existing).
     *
     * @param array<string, string> $variantPaths
     */
    public static function createFromUpload(
        \Illuminate\Database\Eloquent\Model $model,
        array $variantPaths,
        string $filename,
        string $mimeType = 'image/jpeg',
        ?array $metadata = null
    ): bool {
        app(\App\Actions\Avatar\CreateAvatarsFromUploadAction::class)->handle(
            $model,
            $variantPaths,
            $filename,
            $mimeType,
            $metadata
        );

        return true;
    }
}
