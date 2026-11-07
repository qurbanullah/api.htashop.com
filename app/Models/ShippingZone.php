<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A geographic pricing region. Locations narrow the zone to countries, cities,
 * states or postal prefixes; the most specific matching location wins.
 */
class ShippingZone extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'tenant_id',
        'name',
        'code',
        'description',
        'is_active',
        'sort_order',
        'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $zone): void {
            if (empty($zone->uuid)) {
                $zone->uuid = (string) Str::uuid();
            }

            if (empty($zone->code) && filled($zone->name)) {
                $zone->code = Str::slug($zone->name);
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(ShippingZoneLocation::class);
    }

    public function rates(): HasMany
    {
        return $this->hasMany(ShippingRate::class);
    }
}
