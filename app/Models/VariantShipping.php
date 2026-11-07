<?php

namespace App\Models;

use App\Support\Shipping\UnitConverter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-variant logistics override. Anything left null falls back to the product
 * shipping record, which is where the bulk of the data lives.
 */
class VariantShipping extends Model
{
    protected $table = 'variant_shipping';

    protected $fillable = [
        'variant_id',
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

    public function variant(): BelongsTo
    {
        return $this->belongsTo(Variant::class);
    }

    public function weightUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'weight_unit_id');
    }

    public function dimensionUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'dimension_unit_id');
    }
}
