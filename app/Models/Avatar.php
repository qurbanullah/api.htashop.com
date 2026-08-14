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
}
