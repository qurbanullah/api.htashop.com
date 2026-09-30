<?php

namespace App\Http\Controllers\V1\Account;

use App\Enums\RefreshTokenRevokedReasonEnum;
use App\Http\Controllers\Controller;
use App\Http\Responses\V1\ApiResponse;
use App\Models\User;
use App\Services\Auth\RefreshTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Buyer account endpoints — profile and security.
 */
class AccountController extends Controller
{
    public function profile(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing('avatars');

        return ApiResponse::success([
            'id' => $user->id,
            'uuid' => $user->uuid,
            'name' => $user->name,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'email_verified_at' => $user->email_verified_at,
            'avatar_url' => $user->avatar_url,
            'avatar_thumb' => $user->avatar_thumb,
            'avatar_small' => $user->avatar_small,
            'avatar_medium' => $user->avatar_medium,
            'avatar_urls' => $user->getAvatarUrls(),
            'created_at' => $user->created_at,
        ], 'Profile retrieved successfully');
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $user->update([
            'name' => trim($data['name']),
            'first_name' => $data['first_name'] ?? null,
            'last_name' => $data['last_name'] ?? null,
        ]);

        return ApiResponse::success([
            'id' => $user->id,
            'uuid' => $user->uuid,
            'name' => $user->name,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
        ], 'Profile updated successfully');
    }

    public function changePassword(Request $request, RefreshTokenService $refreshTokenService): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        /** @var User $user */
        $user = $request->user();

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $user->update(['password' => Hash::make($data['new_password'])]);

        // Changing a password is often a reaction to suspecting someone else has
        // it, so every *other* session is cut off. This device keeps its session:
        // the user just proved the old password on it.
        $token = $user->token();
        $refreshTokenService->revokeOtherSessionsForUser(
            $user,
            isset($token->id) ? (string) $token->id : null,
            RefreshTokenRevokedReasonEnum::PASSWORD_CHANGED
        );

        return ApiResponse::success(null, 'Password changed successfully');
    }

    /**
     * Soft-delete the authenticated account after confirming the password.
     * All active tokens are revoked so the user is signed out everywhere.
     */
    public function deactivateAccount(Request $request, RefreshTokenService $refreshTokenService): JsonResponse
    {
        $data = $request->validate([
            'password' => ['required', 'string'],
        ]);

        /** @var User $user */
        $user = $request->user();

        if (! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['The password is incorrect.'],
            ]);
        }

        // The row is soft-deleted, so nothing cascades: both the Passport tokens
        // and the refresh tokens have to be retired explicitly.
        $refreshTokenService->revokeAllForUser($user, RefreshTokenRevokedReasonEnum::ACCOUNT_DEACTIVATED);
        $user->tokens()->delete();
        $user->delete();

        return ApiResponse::success(null, 'Account deactivated successfully');
    }
}
