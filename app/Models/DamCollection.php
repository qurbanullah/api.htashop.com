<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class DamCollection extends Model
{
        use HasFactory;

    protected $fillable = [
        'uuid',
        'key',
        'name',
        'kind',
        'description',
        'metadata',
        'is_active',
        'is_system',
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_active' => 'boolean',
        'is_system' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (DamCollection $collection): void {
            if (empty($collection->uuid)) {
                $collection->uuid = (string) Str::uuid();
            }
        });
    }

    public function dams(): BelongsToMany
    {
        return $this->belongsToMany(Dam::class, 'dam_collection_items')
            ->withPivot('sort_order')
            ->withTimestamps();
    }
}
