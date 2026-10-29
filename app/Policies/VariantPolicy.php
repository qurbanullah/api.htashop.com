<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Variant;

class VariantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->activeMemberships()->exists();
    }

    public function view(User $user, Variant $variant): bool
    {
        $product = $variant->product;

        return $user->isAdmin()
            || ($product !== null && $user->hasActiveMembershipInOrganization((int) $product->organization_id));
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Variant $variant): bool
    {
        return $this->view($user, $variant);
    }

    public function delete(User $user, Variant $variant): bool
    {
        return $this->view($user, $variant);
    }
}
