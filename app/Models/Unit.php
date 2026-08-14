<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Unit extends Model
{
        use HasFactory;

    protected $fillable = [
        'uuid',
        'tenant_id',
        'measurement_id',
        'name',
        'code',
        'symbol',
        'factor',
        'offset',
        'precision',
        'is_active',
    ];

    protected $casts = [
        'factor' => 'decimal:10',
        'offset' => 'decimal:10',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Unit $unit): void {
            if (empty($unit->uuid)) {
                $unit->uuid = (string) Str::uuid();
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function measurement(): BelongsTo
    {
        return $this->belongsTo(Measurement::class, 'measurement_id');
    }

    public function definitions(): HasMany
    {
        return $this->hasMany(Definition::class);
    }

    public function values(): HasMany
    {
        return $this->hasMany(Value::class);
    }
}
