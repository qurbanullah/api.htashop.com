<?php

namespace App\Policies;

use App\Models\Code;
use App\Models\Membership;
use App\Models\User;

class CodePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['super-admin', 'admin']) || Membership::query()->where('user_id', $user->id)->where('is_active', true)->exists();
    }

    public function view(User $user, Code $code): bool
    {
        return $this->ownsScope($user, $code->tenant_id, $code->organization_id);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Code $code): bool
    {
        return $this->ownsScope($user, $code->tenant_id, $code->organization_id);
    }

    public function delete(User $user, Code $code): bool
    {
        return $this->ownsScope($user, $code->tenant_id, $code->organization_id);
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
