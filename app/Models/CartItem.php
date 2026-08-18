<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CartItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'cart_id',
        'product_id',
        'variant_id',
        'quantity',
        'unit_price',
        'base_price',
        'currency',
        'metadata',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'base_price' => 'decimal:2',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (CartItem $item): void {
            if (empty($item->uuid)) {
                $item->uuid = (string) Str::uuid();
            }
        });
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
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
