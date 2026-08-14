<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Attachment extends Model
{
        use HasFactory, SoftDeletes;

    protected $fillable = [
        'attachable_type',
        'attachable_id',
        'name',
        'filename',
        'path',
        'type',
        'mime_type',
        'size',
        'extension',
        'description',
        'is_confidential',
        'version',
        'sort_order',
        'metadata',
        'uploaded_by'
    ];

    protected $casts = [
        'is_confidential' => 'boolean',
        'size' => 'integer',
        'version' => 'integer',
        'sort_order' => 'integer',
        'metadata' => 'array'
    ];

    protected $attributes = [
        'is_confidential' => false,
        'version' => 1,
        'sort_order' => 0
    ];

    /**
     * Get the attachable entity (manuscript, submission, etc.)
     */
    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the user who uploaded this attachment
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Scope confidential attachments
     */
    public function scopeConfidential($query)
    {
        return $query->where('is_confidential', true);
    }

    /**
     * Scope by type
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Get human readable file size
     */
    public function getHumanSizeAttribute(): string
    {
        $bytes = $this->size;
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }
}
