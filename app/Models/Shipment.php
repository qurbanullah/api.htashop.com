<?php

namespace App\Models;

use App\Enums\ShipmentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * One dispatch of (part of) an order, packed into one or more parcels.
 *
 * `ship_to` is a snapshot taken when the shipment is created: the address book
 * may change afterwards, but where the parcel went must not.
 */
class Shipment extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'tenant_id',
        'warehouse_id',
        'order_id',
        'status',
        'carrier',
        'service',
        'tracking_number',
        'tracking_url',
        'carrier_booking_id',
        'label_url',
        'weight_kg',
        'ship_from',
        'ship_to',
        'notes',
        'created_by',
        'packed_at',
        'shipped_at',
        'delivered_at',
        'booked_at',
        'metadata',
    ];

    protected $casts = [
        'weight_kg' => 'decimal:3',
        'ship_from' => 'array',
        'ship_to' => 'array',
        'packed_at' => 'datetime',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
        'booked_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $shipment): void {
            if (empty($shipment->uuid)) {
                $shipment->uuid = (string) Str::uuid();
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

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ShipmentItem::class);
    }

    public function parcels(): HasMany
    {
        return $this->hasMany(Parcel::class)->orderBy('sequence');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isOpen(): bool
    {
        return ShipmentStatus::isOpen((string) $this->status);
    }

    /** Sum of parcel weights, or null while no parcel has a weight. */
    public function parcelWeightKg(): ?float
    {
        $parcels = $this->relationLoaded('parcels') ? $this->parcels : $this->parcels()->get();

        $weights = $parcels
            ->pluck('weight_kg')
            ->filter(fn ($weight) => $weight !== null)
            ->map(fn ($weight) => (float) $weight);

        return $weights->isEmpty() ? null : round($weights->sum(), 3);
    }

    public function recalculateWeight(): void
    {
        $this->weight_kg = $this->parcelWeightKg();
    }
}
