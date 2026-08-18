<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Cart extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'user_id',
        'session_id',
        'currency',
        'status',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    protected $attributes = [
        'status' => 'active',
        'currency' => 'USD',
    ];

    protected static function booted(): void
    {
        static::creating(function (Cart $cart): void {
            if (empty($cart->uuid)) {
                $cart->uuid = (string) Str::uuid();
            }

            if (empty($cart->session_id) && empty($cart->user_id)) {
                $cart->session_id = (string) Str::uuid();
            }
        });
    }

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
