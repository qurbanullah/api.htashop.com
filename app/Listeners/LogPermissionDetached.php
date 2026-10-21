<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\Permission;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Events\PermissionDetached;

class LogPermissionDetached
{
    public function __construct(
        protected AuditLogger $auditLogger,
    ) {}

    public function handle(PermissionDetached $event): void
    {
        $model = $event->model;
        $permissionsOrIds = $event->permissionsOrIds;
        $permissions = is_array($permissionsOrIds) ? $permissionsOrIds : [$permissionsOrIds];
        $actor = Auth::guard('api')->user() ?? Auth::user();

        foreach ($permissions as $permissionOrId) {
            $permission = $permissionOrId instanceof Permission
                ? $permissionOrId
                : (is_numeric($permissionOrId) || is_string($permissionOrId) ? Permission::find($permissionOrId) : null);

            if (! $permission) {
                continue;
            }

            $this->auditLogger->record($actor, 'permission.detached', $model, [
                'old_values' => [
                    'permission_id' => $permission->id,
                    'permission_name' => $permission->name,
                ],
                'description' => "Permission '{$permission->name}' was detached from ".class_basename($model),
            ]);
        }
    }
}
