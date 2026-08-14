<?php

namespace App\Policies;

use App\Models\Assignment;
use App\Models\Membership;
use App\Models\User;

class AssignmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['super-admin', 'admin']) || Membership::query()->where('user_id', $user->id)->where('is_active', true)->exists();
    }

    public function view(User $user, Assignment $assignment): bool
    {
        return $this->ownsScope($user, $assignment->tenant_id, $assignment->organization_id);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Assignment $assignment): bool
    {
        return $this->ownsScope($user, $assignment->tenant_id, $assignment->organization_id);
    }

    public function delete(User $user, Assignment $assignment): bool
    {
        return $this->ownsScope($user, $assignment->tenant_id, $assignment->organization_id);
    }

    private function ownsScope(User $user, int $tenantId, ?int $organizationId): bool
    {
        if ($user->hasRole(['super-admin', 'admin'])) {
            return true;
        }

        $query = Membership::query()->where('user_id', $user->id)->where('tenant_id', $tenantId)->where('is_active', true);

        if ($organizationId) {
            $query->where('organization_id', $organizationId);
        }

        return $query->exists();
    }
}
