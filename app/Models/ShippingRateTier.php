<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One weight bracket of a `tiered` shipping rate.
 *
 * `min_weight_kg` is inclusive, `max_weight_kg` exclusive; a null maximum is
 * open-ended and must be the last tier.
 */
class ShippingRateTier extends Model
{
    use HasFactory;

    protected $fillable = [
        'shipping_rate_id',
        'min_weight_kg',
        'max_weight_kg',
        'amount',
    ];

    protected $casts = [
        'min_weight_kg' => 'decimal:3',
        'max_weight_kg' => 'decimal:3',
        'amount' => 'decimal:2',
    ];

    public function rate(): BelongsTo
    {
        return $this->belongsTo(ShippingRate::class, 'shipping_rate_id');
    }

    public function covers(float $weightKg): bool
    {
        if ($weightKg < (float) $this->min_weight_kg) {
            return false;
        }

        return $this->max_weight_kg === null || $weightKg < (float) $this->max_weight_kg;
    }
}
