<?php

namespace App\Models;

use App\Enums\CouponType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * A discount code.
 *
 * Whether a coupon *may* be used is decided by `App\Services\Coupon\CouponService`
 * (dates, limits, minimum spend); this model holds the data and the small
 * amount of structure that makes it queryable.
 */
class Coupon extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'tenant_id',
        'organization_id',
        'code',
        'label',
        'type',
        'value',
        'min_order_amount',
        'max_discount_amount',
        'starts_at',
        'ends_at',
        'usage_limit',
        'per_user_limit',
        'used_count',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'tenant_id' => 'integer',
        'organization_id' => 'integer',
        'value' => 'decimal:2',
        'min_order_amount' => 'decimal:2',
        'max_discount_amount' => 'decimal:2',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'usage_limit' => 'integer',
        'per_user_limit' => 'integer',
        'used_count' => 'integer',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (Coupon $coupon): void {
            if (empty($coupon->uuid)) {
                $coupon->uuid = (string) Str::uuid();
            }
        });

        // Normalise on the way in so `save10` cannot shadow `SAVE10`.
        static::saving(function (Coupon $coupon): void {
            $coupon->code = self::normaliseCode($coupon->code);

            // An organization-scoped code always belongs to that organization's
            // tenant. Deriving it here (rather than trusting the request) keeps
            // "list tenant T's coupons" correct and stops a mismatched pair
            // from ever reaching the unique index.
            if ($coupon->organization_id !== null && $coupon->tenant_id === null) {
                $coupon->tenant_id = Organization::query()
                    ->whereKey($coupon->organization_id)
                    ->value('tenant_id');
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public static function normaliseCode(?string $code): string
    {
        return Str::upper(trim((string) $code));
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }

    /** Null for a platform-wide or organization-scoped code. */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** Null for a platform-wide or tenant-scoped code. */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Restrict to the codes a shopper in this scope may see: their
     * organization's own codes, their tenant's codes and the global ones.
     *
     * This is a *visibility* filter. Which one wins when several match the same
     * code is decided by `scopeOrderBySpecificity()` and, ultimately, by
     * `CouponService::resolve()`.
     */
    public function scopeWithinScope(Builder $query, ?int $tenantId, ?int $organizationId): Builder
    {
        return $query->where(function (Builder $inner) use ($tenantId, $organizationId): void {
            $inner->where(fn (Builder $global) => $global
                ->whereNull('tenant_id')
                ->whereNull('organization_id'));

            if ($tenantId !== null) {
                $inner->orWhere(fn (Builder $tenant) => $tenant
                    ->where('tenant_id', $tenantId)
                    ->whereNull('organization_id'));
            }

            if ($organizationId !== null) {
                $inner->orWhere('organization_id', $organizationId);
            }
        });
    }

    /**
     * Most specific first: an organization's code beats its tenant's, which
     * beats the global code. Lets one query answer "the" coupon for a code
     * without three round trips.
     */
    public function scopeOrderBySpecificity(Builder $query): Builder
    {
        return $query->orderByRaw(
            'case when organization_id is not null then 2 when tenant_id is not null then 1 else 0 end desc'
        );
    }

    /** `global`, `tenant` or `organization` — for admin lists and order metadata. */
    public function scopeLabel(): string
    {
        if ($this->organization_id !== null) {
            return 'organization';
        }

        if ($this->tenant_id !== null) {
            return 'tenant';
        }

        return 'global';
    }

    /** Not archived or switched off. Dates and limits are the service's job. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function isPercent(): bool
    {
        return $this->type === CouponType::PERCENT;
    }

    /** `10% off` / `PKR 500 off` — for admin lists and order metadata. */
    public function describe(): string
    {
        $value = (float) $this->value;

        return $this->isPercent()
            ? rtrim(rtrim(number_format($value, 2), '0'), '.').'% off'
            : rtrim(rtrim(number_format($value, 2), '0'), '.').' off';
    }

    public function hasReachedUsageLimit(): bool
    {
        return $this->usage_limit !== null && $this->used_count >= $this->usage_limit;
    }

    /**
     * A single word for the admin list: whether this code applies right now,
     * and if not, why. Derived, never stored — the flags and dates are the
     * source of truth.
     */
    public function status(): string
    {
        if (! $this->is_active) {
            return 'inactive';
        }

        $now = now();

        if ($this->starts_at && $this->starts_at->isAfter($now)) {
            return 'scheduled';
        }

        if ($this->ends_at && $this->ends_at->isBefore($now)) {
            return 'expired';
        }

        if ($this->hasReachedUsageLimit()) {
            return 'exhausted';
        }

        return 'active';
    }
}
