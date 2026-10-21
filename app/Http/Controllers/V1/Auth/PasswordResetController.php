<?php

namespace App\Http\Controllers\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Responses\V1\ApiResponse;
use App\Models\User;
use App\Services\Audit\AuditLogger;
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
            $this->audit('auth.password_reset_requested', $user, [
                'description' => 'Password reset link requested',
                'meta' => [
                    'email_hash' => hash('sha256', strtolower((string) $user->email)),
                ],
            ]);

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

                $this->audit('auth.password.reset', $user, [
                    'description' => 'Password reset completed',
                ]);
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return ApiResponse::success(null, 'Your password has been reset successfully.');
        }

        return ApiResponse::error(__($status), null, 400);
    }

    /**
     * Audit writes are best-effort here: a storage failure must not turn a
     * successful password reset into a 500.
     */
    private function audit(string $event, User $user, array $meta = []): void
    {
        try {
            app(AuditLogger::class)->record($user, $event, $user, $meta);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}
