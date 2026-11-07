<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A delivery option priced by the quote engine.
 *
 * `calculation` decides the arithmetic:
 *   flat        — `base_amount`
 *   per_weight  — `base_amount` + any kg above `included_weight_kg` × `per_kg_amount`
 *   tiered      — the matching `shipping_rate_tiers` row
 *
 * The rate is only offered when the parcel's billable weight falls inside the
 * optional `min_weight_kg` / `max_weight_kg` window. A null `shipping_zone_id`
 * makes the rate a fallback for any destination.
 */
class ShippingRate extends Model
{
    use HasFactory;

    public const CALC_FLAT = 'flat';

    public const CALC_PER_WEIGHT = 'per_weight';

    public const CALC_TIERED = 'tiered';

    public const CARRIER_MANUAL = 'manual';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'shipping_zone_id',
        'warehouse_id',
        'name',
        'code',
        'description',
        'carrier',
        'calculation',
        'currency',
        'base_amount',
        'included_weight_kg',
        'per_kg_amount',
        'min_weight_kg',
        'max_weight_kg',
        'free_over_amount',
        'estimated_days_min',
        'estimated_days_max',
        'priority',
        'is_active',
        'carries_hazmat',
        'metadata',
    ];

    protected $casts = [
        'base_amount' => 'decimal:2',
        'included_weight_kg' => 'decimal:3',
        'per_kg_amount' => 'decimal:2',
        'min_weight_kg' => 'decimal:3',
        'max_weight_kg' => 'decimal:3',
        'free_over_amount' => 'decimal:2',
        'estimated_days_min' => 'integer',
        'estimated_days_max' => 'integer',
        'priority' => 'integer',
        'is_active' => 'boolean',
        'carries_hazmat' => 'boolean',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $rate): void {
            if (empty($rate->uuid)) {
                $rate->uuid = (string) Str::uuid();
            }

            if (empty($rate->code) && filled($rate->name)) {
                $rate->code = Str::slug($rate->name);
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

    public function zone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class, 'shipping_zone_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function tiers(): HasMany
    {
        return $this->hasMany(ShippingRateTier::class)->orderBy('min_weight_kg');
    }

    /** False when the parcel weight falls outside the configured window. */
    public function appliesToWeight(?float $billableWeightKg): bool
    {
        if ($billableWeightKg !== null) {
            if ($this->min_weight_kg !== null && $billableWeightKg < (float) $this->min_weight_kg) {
                return false;
            }

            if ($this->max_weight_kg !== null && $billableWeightKg > (float) $this->max_weight_kg) {
                return false;
            }
        } elseif ($this->min_weight_kg !== null || $this->max_weight_kg !== null) {
            // A bounded window cannot be proven to fit an unmeasured parcel.
            return false;
        }

        if ($this->calculation === self::CALC_FLAT) {
            return true;
        }

        // Weight-based calculations need a billable weight to do arithmetic.
        return $billableWeightKg !== null;
    }

    /** Priced amount, or null when this rate cannot price the parcel. */
    public function amountFor(?float $billableWeightKg): ?float
    {
        if (! $this->appliesToWeight($billableWeightKg)) {
            return null;
        }

        $amount = match ($this->calculation) {
            self::CALC_PER_WEIGHT => $this->perWeightAmount($billableWeightKg),
            self::CALC_TIERED => $this->tieredAmount($billableWeightKg),
            default => (float) $this->base_amount,
        };

        return $amount === null ? null : round(max(0.0, $amount), 2);
    }

    public function isFreeOver(float $subtotal): bool
    {
        return $this->free_over_amount !== null
            && $subtotal >= (float) $this->free_over_amount;
    }

    private function perWeightAmount(?float $billableWeightKg): ?float
    {
        if ($billableWeightKg === null) {
            return null;
        }

        $chargeable = max(0.0, $billableWeightKg - (float) $this->included_weight_kg);

        return (float) $this->base_amount + ($chargeable * (float) $this->per_kg_amount);
    }

    private function tieredAmount(?float $billableWeightKg): ?float
    {
        if ($billableWeightKg === null) {
            return null;
        }

        $tiers = $this->relationLoaded('tiers') ? $this->tiers : $this->tiers()->get();
        $tier = $tiers->first(fn (ShippingRateTier $candidate) => $candidate->covers($billableWeightKg));

        return $tier ? (float) $tier->amount : null;
    }
}
