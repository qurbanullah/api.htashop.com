<?php

namespace App\Actions\Users;

use App\Models\User;
use App\Services\User\UserService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class DeleteUserAction
{
    public function __construct(
        protected UserService $userService
    ) {}

    /**
     * Execute the action to soft delete a user
     */
    public function execute(User $user): bool
    {
        try {
            $result = $this->userService->deleteUser($user);

            // Log the action
            Log::info('User soft deleted', [
                'user_id' => $user->id,
                'email' => $user->email,
                'deleted_by' => Auth::id(),
            ]);

            return $result;
        } catch (\Exception $e) {
            Log::error('Failed to delete user', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
