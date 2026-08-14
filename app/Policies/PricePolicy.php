<?php

namespace App\Policies;

use App\Models\Membership;
use App\Models\Price;
use App\Models\User;

class PricePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['super-admin', 'admin']) || Membership::query()->where('user_id', $user->id)->where('is_active', true)->exists();
    }

    public function view(User $user, Price $price): bool
    {
        return $this->ownsScope($user, $price->tenant_id, $price->organization_id);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Price $price): bool
    {
        return $this->ownsScope($user, $price->tenant_id, $price->organization_id);
    }

    public function delete(User $user, Price $price): bool
    {
        return $this->ownsScope($user, $price->tenant_id, $price->organization_id);
    }

    private function ownsScope(User $user, int $tenantId, int $organizationId): bool
    {
        if ($user->hasRole(['super-admin', 'admin'])) {
            return true;
        }

        return Membership::query()->where('user_id', $user->id)->where('tenant_id', $tenantId)->where('organization_id', $organizationId)->where('is_active', true)->exists();
    }
}
