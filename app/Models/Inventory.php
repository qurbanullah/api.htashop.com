<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Inventory extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid', 'tenant_id', 'warehouse_id',
        'stockable_type', 'stockable_id',
        'sku', 'quantity', 'reserved', 'low_stock_threshold',
        'track_inventory', 'is_active', 'metadata',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'reserved' => 'decimal:3',
        'low_stock_threshold' => 'decimal:3',
        'track_inventory' => 'boolean',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (Inventory $inventory): void {
            if (empty($inventory->uuid)) {
                $inventory->uuid = (string) Str::uuid();
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function stockable(): MorphTo
    {
        return $this->morphTo();
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class);
    }

    /**
     * Available quantity = on-hand minus reserved.
     */
    public function getAvailableAttribute(): float
    {
        return (float) ($this->quantity - $this->reserved);
    }

    public function isLowStock(): bool
    {
        return $this->low_stock_threshold !== null
            && $this->getAvailableAttribute() <= (float) $this->low_stock_threshold;
    }
}
