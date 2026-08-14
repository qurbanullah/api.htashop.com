<?php

namespace App\Http\Controllers\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Responses\V1\ApiResponse;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

class PasswordResetController extends Controller
{
    public function checkAccount(\App\Http\Requests\V1\Auth\CheckAccount\CheckAccountRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if ($user) {
            return ApiResponse::success([
                'exists' => true,
                'name' => $user->name,
                'registered_at' => $user->created_at->format('F Y'),
            ], 'An account exists with this email address.');
        }

        return ApiResponse::success([
            'exists' => false,
        ], 'No account found with this email address.');
    }

    public function sendResetLink(\App\Http\Requests\V1\Auth\SendResetLinkRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (! $user) {
            return ApiResponse::error('No account found with this email address.', null, 404);
        }

        $status = Password::sendResetLink($request->only('email'));

        if ($status === Password::RESET_LINK_SENT) {
            return ApiResponse::success(null, 'Password reset link sent to your email address.');
        }

        return ApiResponse::error('Failed to send password reset link. Please try again.', null, 500);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->save();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return ApiResponse::success(null, 'Your password has been reset successfully.');
        }

        return ApiResponse::error(__($status), null, 400);
    }
}
