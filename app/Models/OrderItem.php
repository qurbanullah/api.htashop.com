<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class OrderItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'order_id',
        'product_id',
        'variant_id',
        'name',
        'sku',
        'quantity',
        'unit_price',
        'base_price',
        'total',
        'currency',
        'metadata',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_price' => 'decimal:2',
        'base_price' => 'decimal:2',
        'total' => 'decimal:2',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (OrderItem $item): void {
            if (empty($item->uuid)) {
                $item->uuid = (string) Str::uuid();
            }
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(Variant::class);
    }
}
