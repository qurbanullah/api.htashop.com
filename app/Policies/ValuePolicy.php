<?php

namespace App\Policies;

use App\Models\Membership;
use App\Models\Product;
use App\Models\User;
use App\Models\Value;

class ValuePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['super-admin', 'admin']) || Membership::query()->where('user_id', $user->id)->where('is_active', true)->exists();
    }

    public function view(User $user, Value $value): bool
    {
        return $this->ownsValue($user, $value);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Value $value): bool
    {
        return $this->ownsValue($user, $value);
    }

    public function delete(User $user, Value $value): bool
    {
        return $this->ownsValue($user, $value);
    }

    private function ownsValue(User $user, Value $value): bool
    {
        if ($user->hasRole(['super-admin', 'admin'])) {
            return true;
        }

        $organizationId = $value->valuable instanceof Product
            ? $value->valuable->organization_id
            : $value->valuable?->product?->organization_id;

        return Membership::query()->where('user_id', $user->id)->where('tenant_id', $value->tenant_id)->where('organization_id', $organizationId)->where('is_active', true)->exists();
    }
}
