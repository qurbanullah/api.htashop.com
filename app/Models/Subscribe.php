<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Subscribe extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'tenant_id',
        'type',
        'email',
        'subscribable_type',
        'subscribable_id',
        'product_id',
        'variant_id',
        'quantity',
        'amount',
        'currency',
        'contract_id',
        'frequency',
        'interval',
        'shipping_address',
        'billing_address',
        'payment_method',
        'status',
        'starts_at',
        'next_run_at',
        'ends_at',
        'metadata',
        'type',
        'is_subscribed',
        'subscribed_at',
        'unsubscribed_at',
        'unsubscribe_token',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'amount' => 'decimal:2',
        'interval' => 'integer',
        'shipping_address' => 'array',
        'billing_address' => 'array',
        'starts_at' => 'datetime',
        'next_run_at' => 'datetime',
        'ends_at' => 'datetime',
        'metadata' => 'array',
        'is_subscribed' => 'boolean',
        'subscribed_at' => 'datetime',
        'unsubscribed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Subscribe $subscribe): void {
            if (empty($subscribe->uuid)) {
                $subscribe->uuid = (string) Str::uuid();
            }
            if (empty($subscribe->unsubscribe_token)) {
                $subscribe->unsubscribe_token = (string) Str::uuid();
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

    /**
     * The entity that owns this subscription (User, Organization, etc.).
     */
    public function subscribable(): MorphTo
    {
        return $this->morphTo();
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(Variant::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    /**
     * Mark the subscription as opted-in.
     */
    public function subscribe(): bool
    {
        $this->is_subscribed = true;
        $this->subscribed_at = now();
        $this->unsubscribed_at = null;

        return $this->save();
    }

    /**
     * Mark the subscription as opted-out.
     */
    public function unsubscribe(): bool
    {
        $this->is_subscribed = false;
        $this->unsubscribed_at = now();

        return $this->save();
    }
}
