<?php

namespace App\Policies;

use App\Models\ProductImport;
use App\Models\User;

/**
 * An import belongs to one tenant/organization. Members of that organization —
 * or a platform admin — may read it and may commit or cancel it.
 */
class ProductImportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->activeMemberships()->exists();
    }

    public function view(User $user, ProductImport $import): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($import->organization_id !== null) {
            return $user->hasActiveMembershipInOrganization((int) $import->organization_id);
        }

        return $user->hasActiveMembershipInTenant((int) $import->tenant_id);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, ProductImport $import): bool
    {
        return $this->view($user, $import);
    }

    public function delete(User $user, ProductImport $import): bool
    {
        return $this->view($user, $import);
    }
}
