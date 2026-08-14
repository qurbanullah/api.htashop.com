<?php

namespace App\Actions\Users;

use App\Models\User;
use App\Services\User\UserService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class UpdateUserAction
{
    public function __construct(
        protected UserService $userService,
        protected \App\Services\Audit\AuditService $auditService
    ) {}

    /**
     * Execute the action to update a user
     */
    public function execute(User $user, array $data, ?User $actor = null): User
    {
        try {
            DB::beginTransaction();

            $originalData = $user->only(['name', 'email']);

            $updatedUser = $this->userService->updateUser($user, $data);

            // Log the action
            Log::info('User updated', [
                'user_id' => $user->id,
                'original_data' => $originalData,
                'updated_by' => Auth::id(),
            ]);

            // Record audit with old and new values
            try {
                $actor = $actor ?: request()->user('api') ?: Auth::guard('api')->user() ?: request()->user() ?: Auth::user();
                $this->auditService->record($actor, 'user.updated', $user, [
                    'event' => 'updated',
                    'auditable_type_name' => 'User',
                    'old_values' => $originalData,
                    'new_values' => $updatedUser->only(array_keys($originalData)),
                    'description' => 'User updated via admin UI',
                ]);
            } catch (\Throwable $e) {
                Log::warning('Failed to record audit for user update', ['error' => $e->getMessage()]);
            }

            DB::commit();

            return $updatedUser;
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to update user', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
