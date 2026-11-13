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
        'base_unit_id',
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

    /**
     * The unit every other unit of this measurement is expressed in.
     *
     * `Unit::factor` is relative to this unit, so `normalized_number` is a value
     * in `base_unit_id`.
     */
    public function baseUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'base_unit_id');
    }

    public function definitions(): HasMany
    {
        return $this->hasMany(Definition::class, 'measurement_id');
    }
}
