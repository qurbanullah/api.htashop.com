<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Measurement extends Model
{
        use HasFactory;

    protected $table = 'measurements';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'name',
        'code',
        'description',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (Measurement $measurement): void {
            if (empty($measurement->uuid)) {
                $measurement->uuid = (string) Str::uuid();
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function units(): HasMany
    {
        return $this->hasMany(Unit::class, 'measurement_id');
    }

    public function definitions(): HasMany
    {
        return $this->hasMany(Definition::class, 'measurement_id');
    }
}
