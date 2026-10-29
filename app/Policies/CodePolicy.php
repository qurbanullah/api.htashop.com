<?php

namespace App\Policies;

use App\Models\Code;
use App\Models\User;

class CodePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->activeMemberships()->exists();
    }

    public function view(User $user, Code $code): bool
    {
        return $this->ownsScope($user, (int) $code->tenant_id, $code->organization_id !== null ? (int) $code->organization_id : null);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Code $code): bool
    {
        return $this->view($user, $code);
    }

    public function delete(User $user, Code $code): bool
    {
        return $this->view($user, $code);
    }

    private function ownsScope(User $user, int $tenantId, ?int $organizationId): bool
    {
        return $user->isAdmin()
            || ($user->hasActiveMembershipInTenant($tenantId)
                && ($organizationId === null || $user->hasActiveMembershipInOrganization($organizationId)));
    }
}
