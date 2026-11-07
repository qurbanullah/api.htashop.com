<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * One physical box in a shipment.
 *
 * `packaging_template_id` records which reusable box was used; dimensions and
 * weight are copied in so a later template edit does not rewrite history.
 */
class Parcel extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'shipment_id',
        'packaging_template_id',
        'sequence',
        'length_cm',
        'width_cm',
        'height_cm',
        'weight_kg',
        'contains_hazmat',
        'tracking_number',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'sequence' => 'integer',
        'length_cm' => 'decimal:2',
        'width_cm' => 'decimal:2',
        'height_cm' => 'decimal:2',
        'weight_kg' => 'decimal:3',
        'contains_hazmat' => 'boolean',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $parcel): void {
            if (empty($parcel->uuid)) {
                $parcel->uuid = (string) Str::uuid();
            }
        });
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function packagingTemplate(): BelongsTo
    {
        return $this->belongsTo(PackagingTemplate::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ParcelItem::class);
    }

    public function volumeCm3(): ?float
    {
        if ($this->length_cm === null || $this->width_cm === null || $this->height_cm === null) {
            return null;
        }

        return (float) $this->length_cm * (float) $this->width_cm * (float) $this->height_cm;
    }
}
