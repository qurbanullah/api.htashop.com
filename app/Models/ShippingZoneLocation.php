<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One geographic matcher inside a shipping zone.
 *
 * Resolution order is by specificity: `city` beats `postal_prefix`, which beats
 * `state`, which beats `country`.
 */
class ShippingZoneLocation extends Model
{
    use HasFactory;

    public const TYPE_COUNTRY = 'country';

    public const TYPE_CITY = 'city';

    public const TYPE_STATE = 'state';

    public const TYPE_POSTAL_PREFIX = 'postal_prefix';

    protected $fillable = [
        'shipping_zone_id',
        'type',
        'country_id',
        'city_id',
        'state',
        'postal_code_prefix',
    ];

    public function zone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class, 'shipping_zone_id');
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }
}
