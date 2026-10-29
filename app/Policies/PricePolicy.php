<?php

namespace App\Policies;

use App\Models\Price;
use App\Models\User;

class PricePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->activeMemberships()->exists();
    }

    public function view(User $user, Price $price): bool
    {
        return $this->ownsScope($user, (int) $price->tenant_id, (int) $price->organization_id);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Price $price): bool
    {
        return $this->ownsScope($user, (int) $price->tenant_id, (int) $price->organization_id);
    }

    public function delete(User $user, Price $price): bool
    {
        return $this->ownsScope($user, (int) $price->tenant_id, (int) $price->organization_id);
    }

    private function ownsScope(User $user, int $tenantId, int $organizationId): bool
    {
        return $user->isAdmin()
            || ($user->hasActiveMembershipInTenant($tenantId)
                && $user->hasActiveMembershipInOrganization($organizationId));
    }
}
