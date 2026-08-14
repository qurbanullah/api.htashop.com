<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class Revision extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'revisable_type',
        'revisable_id',
        'revision_number',
        'revision_type',
        'reason',
        'payload',
        'metadata',
        'created_by',
        'created_by_type',
    ];

    protected $casts = [
        'payload' => 'array',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (Revision $revision): void {
            if (empty($revision->uuid)) {
                $revision->uuid = (string) Str::uuid();
            }
        });
    }

    public function revisable(): MorphTo
    {
        return $this->morphTo();
    }

    public function createdBy(): MorphTo
    {
        return $this->morphTo('createdBy');
    }
}
