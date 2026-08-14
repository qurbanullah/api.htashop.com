<?php

namespace App\Actions\Users;

use App\Models\User;
use App\Services\User\UserService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CreateUserAction
{
    public function __construct(
        protected UserService $userService
    ) {}

    /**
     * Execute the action to create a new user
     */
    public function execute(array $data): User
    {
        try {
            DB::beginTransaction();

            $user = $this->userService->createUser($data);

            // Log the action
            Log::info('User created', [
                'user_id' => $user->id,
                'email' => $user->email,
                'created_by' => auth()->id(),
            ]);

            DB::commit();

            return $user;
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to create user', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            throw $e;
        }
    }
}
