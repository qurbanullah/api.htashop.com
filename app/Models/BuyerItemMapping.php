<?php

namespace App\Models;

use App\Traits\Audit\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * "This buyer item is my product" — the tenant-scoped half of the model.
 *
 * The unique index is (tenant_id, buyer_item_id): a buyer's code may map to
 * only one of a tenant's items, while different tenants map it to their own.
 */
class BuyerItemMapping extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'uuid',
        'tenant_id',
        'buyer_party_id',
        'buyer_item_id',
        'product_id',
        'variant_id',
        'is_primary',
        'is_active',
        'effective_from',
        'effective_to',
        'source',
        'metadata',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'is_active' => 'boolean',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (BuyerItemMapping $mapping): void {
            if (empty($mapping->uuid)) {
                $mapping->uuid = (string) Str::uuid();
            }
        });
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(BuyerItem::class, 'buyer_item_id');
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(BuyerParty::class, 'buyer_party_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(Variant::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function isEffectiveOn(?string $date = null): bool
    {
        $date = $date ?? now()->toDateString();

        if ($this->effective_from !== null && $this->effective_from->toDateString() > $date) {
            return false;
        }

        return $this->effective_to === null || $this->effective_to->toDateString() >= $date;
    }

    public function getAuditInclude(): array
    {
        return [
            'buyer_item_id',
            'product_id',
            'variant_id',
            'is_primary',
            'is_active',
            'effective_from',
            'effective_to',
        ];
    }
}
