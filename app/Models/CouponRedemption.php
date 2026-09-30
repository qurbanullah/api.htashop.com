<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * One coupon applied to one order.
 *
 * Append-only: rows are removed (not edited) when an order is cancelled, so a
 * customer's use is returned to them.
 */
class CouponRedemption extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'coupon_id',
        'order_id',
        'user_id',
        'session_id',
        'code',
        'amount',
        'currency',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (CouponRedemption $redemption): void {
            if (empty($redemption->uuid)) {
                $redemption->uuid = (string) Str::uuid();
            }
        });
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    /** Null for a guest — `session_id` identifies them instead. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
