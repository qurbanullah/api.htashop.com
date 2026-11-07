<?php

namespace App\Models;

use App\Support\Shipping\UnitConverter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Logistics data for a product: weights, package dimensions, packaging type,
 * handling flags and the "what's included" list.
 *
 * Raw values keep the unit the editor picked; the normalised `*_kg` / `*_cm`
 * columns are recalculated on save and are what the rate engine queries.
 */
class ProductShipping extends Model
{
    protected $table = 'product_shipping';

    protected $fillable = [
        'product_id',
        'net_weight',
        'gross_weight',
        'weight_unit_id',
        'gross_weight_kg',
        'length',
        'width',
        'height',
        'dimension_unit_id',
        'length_cm',
        'width_cm',
        'height_cm',
        'packaging_type_unit_id',
        'units_per_case',
        'ships_alone',
        'is_fragile',
        'is_stackable',
        'is_hazmat',
        'un_number',
        'hazard_class_option_id',
        'packing_group_option_id',
        'handling_notes',
        'contents',
    ];

    protected $casts = [
        'net_weight' => 'decimal:3',
        'gross_weight' => 'decimal:3',
        'gross_weight_kg' => 'decimal:3',
        'length' => 'decimal:2',
        'width' => 'decimal:2',
        'height' => 'decimal:2',
        'length_cm' => 'decimal:2',
        'width_cm' => 'decimal:2',
        'height_cm' => 'decimal:2',
        'units_per_case' => 'integer',
        'ships_alone' => 'boolean',
        'is_fragile' => 'boolean',
        'is_stackable' => 'boolean',
        'is_hazmat' => 'boolean',
        'contents' => 'array',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $shipping): void {
            $shipping->gross_weight_kg = UnitConverter::toKilograms(
                self::number($shipping->gross_weight),
                $shipping->weight_unit_id,
            );

            $shipping->length_cm = UnitConverter::toCentimetres(
                self::number($shipping->length),
                $shipping->dimension_unit_id,
            );

            $shipping->width_cm = UnitConverter::toCentimetres(
                self::number($shipping->width),
                $shipping->dimension_unit_id,
            );

            $shipping->height_cm = UnitConverter::toCentimetres(
                self::number($shipping->height),
                $shipping->dimension_unit_id,
            );
        });
    }

    private static function number(mixed $value): ?float
    {
        return $value === null || $value === '' ? null : (float) $value;
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function weightUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'weight_unit_id');
    }

    public function dimensionUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'dimension_unit_id');
    }

    public function packagingType(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'packaging_type_unit_id');
    }

    public function hazardClass(): BelongsTo
    {
        return $this->belongsTo(Option::class, 'hazard_class_option_id');
    }

    public function packingGroup(): BelongsTo
    {
        return $this->belongsTo(Option::class, 'packing_group_option_id');
    }

    /**
     * True when the record carries enough data to price a shipment.
     */
    public function isRateable(): bool
    {
        return $this->gross_weight_kg !== null
            && $this->length_cm !== null
            && $this->width_cm !== null
            && $this->height_cm !== null;
    }

    /**
     * Dimetric (volumetric) weight in kilograms for carrier comparison.
     */
    public function volumetricWeightKg(float $divisor = 5000): ?float
    {
        if (! $this->isRateable()) {
            return null;
        }

        $volumeCm3 = (float) $this->length_cm * (float) $this->width_cm * (float) $this->height_cm;

        return round($volumeCm3 / $divisor, 3);
    }
}
