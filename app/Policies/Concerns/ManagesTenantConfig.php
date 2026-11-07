<?php

namespace App\Policies\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Access rules for tenant-scoped shipping configuration.
 *
 * Everyone may read; only an admin or the owning tenant may change a row. A
 * platform-wide row (`tenant_id = null`) is admin-only, because editing it
 * silently changes pricing for every tenant.
 */
trait ManagesTenantConfig
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Model $model): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Model $model): bool
    {
        return $this->manages($user, $model);
    }

    public function delete(User $user, Model $model): bool
    {
        return $this->manages($user, $model);
    }

    protected function manages(User $user, Model $model): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        $tenantId = $model->getAttribute('tenant_id');

        return $tenantId !== null
            && (int) $tenantId === (int) $user->activeMemberships()->value('tenant_id');
    }
}
