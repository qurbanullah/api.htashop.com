<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Events\RoleDetached;

class LogRoleDetached
{
    /**
     * Handle the event.
     */
    public function handle(RoleDetached $event): void
    {
        $model = $event->model;
        $rolesOrIds = $event->rolesOrIds;

        // Handle both single role and multiple roles
        $roles = is_array($rolesOrIds) ? $rolesOrIds : [$rolesOrIds];

        foreach ($roles as $roleOrId) {
            // If it's an ID, fetch the role
            if (is_numeric($roleOrId) || is_string($roleOrId)) {
                $role = \App\Models\Role::find($roleOrId);
                if (!$role) continue;
            } else {
                $role = $roleOrId;
            }

            AuditLog::create([
                'auditable_type' => get_class($model),
                'auditable_id' => $model->getKey(),
                'user_id' => Auth::id(),
                'event' => 'role_detached',
                'auditable_type_name' => class_basename($model),
                'old_values' => [
                    'role_id' => $role->id,
                    'role_name' => $role->name,
                    'action' => 'detached',
                ],
                'new_values' => null,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'description' => "Role '{$role->name}' was detached from " . class_basename($model),
            ]);
        }
    }
}
