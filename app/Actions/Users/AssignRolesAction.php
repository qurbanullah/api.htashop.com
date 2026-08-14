<?php

namespace App\Actions\Users;

use App\Models\User;
use App\Services\User\UserService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class AssignRolesAction
{
    public function __construct(
        protected UserService $userService,
        protected \App\Services\Audit\AuditService $auditService
    ) {}

    /**
     * Execute the action to assign roles to a user
     */
    public function execute(User $user, array $roles, ?User $actor = null): User
    {
        try {
            DB::beginTransaction();

            $originalRoles = $user->roles->pluck('name')->toArray();

            $updatedUser = $this->userService->assignRoles($user, $roles);

            // Log the action
            Log::info('User roles assigned', [
                'user_id' => $user->id,
                'original_roles' => $originalRoles,
                'new_roles' => $roles,
                'assigned_by' => Auth::id(),
            ]);

            // Record audit
            try {
                $actor = $actor ?: request()->user('api') ?: Auth::guard('api')->user() ?: request()->user() ?: Auth::user();
                $this->auditService->record($actor, 'user.assign_roles', $user, [
                    'event' => 'updated',
                    'auditable_type_name' => 'User',
                    'old_values' => ['roles' => $originalRoles],
                    'new_values' => ['roles' => $roles],
                    'description' => 'Roles updated via admin UI',
                ]);
            } catch (\Throwable $e) {
                Log::warning('Failed to record audit for role assignment', ['error' => $e->getMessage()]);
            }

            DB::commit();

            return $updatedUser;
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to assign roles', [
                'user_id' => $user->id,
                'roles' => $roles,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
