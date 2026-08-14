<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Events\PermissionAttached;

class LogPermissionAttached
{
    /**
     * Handle the event.
     */
    public function handle(PermissionAttached $event): void
    {
        $model = $event->model;
        $permissionsOrIds = $event->permissionsOrIds;

        // Handle both single permission and multiple permissions
        $permissions = is_array($permissionsOrIds) ? $permissionsOrIds : [$permissionsOrIds];

        foreach ($permissions as $permissionOrId) {
            // If it's an ID, fetch the permission
            if (is_numeric($permissionOrId) || is_string($permissionOrId)) {
                $permission = \App\Models\Permission::find($permissionOrId);
                if (!$permission) continue;
            } else {
                $permission = $permissionOrId;
            }

            AuditLog::create([
                'auditable_type' => get_class($model),
                'auditable_id' => $model->getKey(),
                'user_id' => Auth::id(),
                'event' => 'permission_attached',
                'auditable_type_name' => class_basename($model),
                'old_values' => null,
                'new_values' => [
                    'permission_id' => $permission->id,
                    'permission_name' => $permission->name,
                    'action' => 'attached',
                ],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'description' => "Permission '{$permission->name}' was attached to " . class_basename($model),
            ]);
        }
    }
}
