<?php

namespace App\Models;

use App\Support\Shipping\UnitConverter;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A reusable box/pallet specification ("Small box", "Carton 40×30×30").
 *
 * Raw values keep the unit the editor picked; `*_cm` and `*_kg` are recomputed
 * on save and are what packing and rating read. A null `tenant_id` marks a
 * platform-wide template every tenant can use.
 */
class PackagingTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'tenant_id',
        'name',
        'code',
        'description',
        'packaging_type_unit_id',
        'length',
        'width',
        'height',
        'dimension_unit_id',
        'length_cm',
        'width_cm',
        'height_cm',
        'max_weight',
        'weight_unit_id',
        'max_weight_kg',
        'tare_weight',
        'tare_weight_unit_id',
        'tare_weight_kg',
        'is_active',
        'sort_order',
        'metadata',
    ];

    protected $casts = [
        'length' => 'decimal:2',
        'width' => 'decimal:2',
        'height' => 'decimal:2',
        'length_cm' => 'decimal:2',
        'width_cm' => 'decimal:2',
        'height_cm' => 'decimal:2',
        'max_weight' => 'decimal:3',
        'max_weight_kg' => 'decimal:3',
        'tare_weight' => 'decimal:3',
        'tare_weight_kg' => 'decimal:3',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $template): void {
            if (empty($template->uuid)) {
                $template->uuid = (string) Str::uuid();
            }

            if (empty($template->code) && filled($template->name)) {
                $template->code = Str::slug($template->name);
            }
        });

        static::saving(function (self $template): void {
            $template->length_cm = UnitConverter::toCentimetres(
                self::number($template->length),
                $template->dimension_unit_id,
            );
            $template->width_cm = UnitConverter::toCentimetres(
                self::number($template->width),
                $template->dimension_unit_id,
            );
            $template->height_cm = UnitConverter::toCentimetres(
                self::number($template->height),
                $template->dimension_unit_id,
            );
            $template->max_weight_kg = UnitConverter::toKilograms(
                self::number($template->max_weight),
                $template->weight_unit_id,
            );
            $template->tare_weight_kg = UnitConverter::toKilograms(
                self::number($template->tare_weight),
                $template->tare_weight_unit_id,
            );
        });
    }

    private static function number(mixed $value): ?float
    {
        return $value === null || $value === '' ? null : (float) $value;
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function packagingType(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'packaging_type_unit_id');
    }

    public function dimensionUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'dimension_unit_id');
    }

    public function weightUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'weight_unit_id');
    }

    public function tareWeightUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'tare_weight_unit_id');
    }

    /** Volume in cubic centimetres, or null when the template has no dimensions. */
    public function volumeCm3(): ?float
    {
        if ($this->length_cm === null || $this->width_cm === null || $this->height_cm === null) {
            return null;
        }

        return (float) $this->length_cm * (float) $this->width_cm * (float) $this->height_cm;
    }
}
