<?php

namespace App\Policies;

use App\Models\Measurement;
use App\Models\Membership;
use App\Models\User;

class MeasurementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['super-admin', 'admin']) || Membership::query()->where('user_id', $user->id)->where('is_active', true)->exists();
    }

    public function view(User $user, Measurement $measurement): bool
    {
        return $this->ownsTenant($user, $measurement->tenant_id);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Measurement $measurement): bool
    {
        return $this->ownsTenant($user, $measurement->tenant_id);
    }

    public function delete(User $user, Measurement $measurement): bool
    {
        return $this->ownsTenant($user, $measurement->tenant_id);
    }

    private function ownsTenant(User $user, ?int $tenantId): bool
    {
        if ($user->hasRole(['super-admin', 'admin'])) {
            return true;
        }

        if (!$tenantId) {
            return false;
        }

        return Membership::query()->where('user_id', $user->id)->where('tenant_id', $tenantId)->where('is_active', true)->exists();
    }
}
