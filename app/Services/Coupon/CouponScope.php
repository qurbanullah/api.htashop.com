<?php

declare(strict_types=1);

namespace App\Services\Coupon;

use App\Models\Coupon;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * The scope a coupon lives in: one organization, one tenant, or the platform.
 *
 * A value object rather than three loose parameters, because "which scope am I
 * acting in" is decided once (from the shopper's cart or the merchant's
 * membership) and then has to be applied consistently to listing, creating and
 * authorization. Keeping it in one place is what stops an admin list from
 * showing codes the merchant cannot actually use, or a merchant from editing
 * another merchant's code.
 *
 * Precedence is most-specific-first: an organization's code beats its
 * organization's tenant's, which beats the global code.
 */
final class CouponScope
{
    public function __construct(
        public readonly ?int $tenantId,
        public readonly ?int $organizationId = null,
    ) {}

    /** No tenant, no organization — the platform's own codes. */
    public static function global(): self
    {
        return new self(null, null);
    }

    /**
     * The scope a signed-in merchant acts in, taken from their active
     * membership. An organization membership outranks a tenant one, so a user
     * who belongs to a specific organization manages that organization's
     * codes, not the whole tenant's.
     */
    public static function forUser(User $user): ?self
    {
        $membership = $user->memberships()
            ->where('is_active', true)
            ->first();

        if ($membership === null) {
            return null;
        }

        if ($membership->organization_id !== null) {
            return new self($membership->tenant_id, $membership->organization_id);
        }

        if ($membership->tenant_id !== null) {
            return new self($membership->tenant_id, null);
        }

        return null;
    }

    public function isGlobal(): bool
    {
        return $this->tenantId === null && $this->organizationId === null;
    }

    /**
     * Narrow a coupon query to exactly this scope. Unlike the checkout
     * visibility filter, this is *not* inclusive of wider scopes: a merchant
     * listing their codes sees their codes, not the platform's.
     *
     * @param  Builder<Coupon>  $query
     * @return Builder<Coupon>
     */
    public function constrain(Builder $query): Builder
    {
        if ($this->organizationId !== null) {
            return $query->where('organization_id', $this->organizationId);
        }

        if ($this->tenantId !== null) {
            return $query->where('tenant_id', $this->tenantId)->whereNull('organization_id');
        }

        return $query;
    }

    /** Whether a coupon belongs to this scope, i.e. the actor may change it. */
    public function owns(Coupon $coupon): bool
    {
        if ($this->organizationId !== null) {
            return (int) $coupon->organization_id === $this->organizationId;
        }

        if ($this->tenantId !== null) {
            return $coupon->organization_id === null
                && (int) $coupon->tenant_id === $this->tenantId;
        }

        // A global actor owns nothing in particular; only an admin manages
        // global codes and that path does not go through ownership checks.
        return false;
    }

    /**
     * The columns that pin a new coupon to this scope. Scope is always derived
     * server-side, never trusted from the request.
     *
     * @return array<string, int|null>
     */
    public function toAttributes(): array
    {
        return [
            'tenant_id' => $this->tenantId,
            'organization_id' => $this->organizationId,
        ];
    }
}
