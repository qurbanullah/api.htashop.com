<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Events\PermissionDetached;

class LogPermissionDetached
{
    /**
     * Handle the event.
     */
    public function handle(PermissionDetached $event): void
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
                'event' => 'permission_detached',
                'auditable_type_name' => class_basename($model),
                'old_values' => [
                    'permission_id' => $permission->id,
                    'permission_name' => $permission->name,
                    'action' => 'detached',
                ],
                'new_values' => null,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'description' => "Permission '{$permission->name}' was detached from " . class_basename($model),
            ]);
        }
    }
}
