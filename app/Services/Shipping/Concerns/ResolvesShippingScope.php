<?php

namespace App\Services\Shipping\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Tenant visibility rules shared by the shipping configuration resources.
 *
 * A null `tenant_id` means "platform-wide": every tenant inherits it but only an
 * admin may create or change it. Merchants always write into their own tenant,
 * whatever the payload says.
 */
trait ResolvesShippingScope
{
    protected function applyReadScope(Builder $query, ?User $user): Builder
    {
        if ($user?->isAdmin()) {
            return $query;
        }

        $tenantId = $user?->activeMemberships()->value('tenant_id');

        return $query->where(function (Builder $inner) use ($tenantId) {
            $inner->whereNull('tenant_id');

            if ($tenantId !== null) {
                $inner->orWhere('tenant_id', $tenantId);
            }
        });
    }

    /**
     * The tenant a new row belongs to: admins may pass `tenant_id` (including
     * null for a global row); merchants are pinned to their membership.
     *
     * @param  array<string, mixed>  $data
     */
    protected function resolveWriteTenant(array $data, ?User $user): ?int
    {
        if ($user?->isAdmin()) {
            return data_get($data, 'tenant_id') !== null
                ? (int) data_get($data, 'tenant_id')
                : null;
        }

        $tenantId = $user?->activeMemberships()->value('tenant_id');

        return $tenantId !== null ? (int) $tenantId : null;
    }
}
