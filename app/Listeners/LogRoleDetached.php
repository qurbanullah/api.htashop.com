<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\Role;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Events\RoleDetached;

class LogRoleDetached
{
    public function __construct(
        protected AuditLogger $auditLogger,
    ) {}

    public function handle(RoleDetached $event): void
    {
        $model = $event->model;
        $rolesOrIds = $event->rolesOrIds;
        $roles = is_array($rolesOrIds) ? $rolesOrIds : [$rolesOrIds];
        $actor = Auth::guard('api')->user() ?? Auth::user();

        foreach ($roles as $roleOrId) {
            $role = $roleOrId instanceof Role
                ? $roleOrId
                : (is_numeric($roleOrId) || is_string($roleOrId) ? Role::find($roleOrId) : null);

            if (! $role) {
                continue;
            }

            $this->auditLogger->record($actor, 'role.detached', $model, [
                'old_values' => [
                    'role_id' => $role->id,
                    'role_name' => $role->name,
                ],
                'description' => "Role '{$role->name}' was detached from ".class_basename($model),
            ]);
        }
    }
}
