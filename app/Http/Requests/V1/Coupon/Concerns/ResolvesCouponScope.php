<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\Coupon\Concerns;

use App\Models\Organization;
use Closure;
use Illuminate\Validation\Rule;

/**
 * Shared scope handling for the coupon write requests.
 *
 * A coupon is scoped to an organization, a tenant or the platform. Both the
 * store and update requests read the scope from the same three inputs, so the
 * rules and the "blank means global" coercion live here once.
 */
trait ResolvesCouponScope
{
    /** A blank form field means "no scope" (global), not scope id `0`. */
    protected function scopeTenantId(): ?int
    {
        $value = $this->input('tenant_id');

        return ($value === null || $value === '') ? null : (int) $value;
    }

    protected function scopeOrganizationId(): ?int
    {
        $value = $this->input('organization_id');

        return ($value === null || $value === '') ? null : (int) $value;
    }

    /**
     * An organization only exists inside one tenant, so a coupon cannot claim
     * tenant A and an organization that lives in tenant B.
     */
    protected function organizationBelongsToTenant(?int $tenantId): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($tenantId): void {
            if ($value === null || $tenantId === null) {
                return;
            }

            $belongs = Organization::query()
                ->whereKey($value)
                ->where('tenant_id', $tenantId)
                ->exists();

            if (! $belongs) {
                $fail('The selected organization does not belong to the selected tenant.');
            }
        };
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function scopeRules(): array
    {
        return [
            'tenant_id' => [
                'nullable', 'integer',
                Rule::exists('tenants', 'id')->whereNull('deleted_at'),
            ],
            'organization_id' => [
                'nullable', 'integer',
                Rule::exists('organizations', 'id')->whereNull('deleted_at'),
                $this->organizationBelongsToTenant($this->scopeTenantId()),
            ],
        ];
    }
}
