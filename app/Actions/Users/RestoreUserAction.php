<?php

namespace App\Actions\Users;

use App\Models\User;
use App\Services\User\UserService;
use Illuminate\Support\Facades\Log;

class RestoreUserAction
{
    public function __construct(
        protected UserService $userService
    ) {}

    /**
     * Execute the action to restore a soft-deleted user
     */
    public function execute(int $userId): ?User
    {
        try {
            $user = $this->userService->restoreUser($userId);

            if ($user) {
                // Log the action
                Log::info('User restored', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'restored_by' => auth()->id(),
                ]);
            }

            return $user;
        } catch (\Exception $e) {
            Log::error('Failed to restore user', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
