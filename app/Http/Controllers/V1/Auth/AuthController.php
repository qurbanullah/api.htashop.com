<?php

namespace App\Http\Controllers\V1\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Responses\V1\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
use App\Notifications\EmailVerificationNotification;

class AuthController extends Controller
{
    /**
     * Register a new user
     */
    public function register(\App\Http\Requests\V1\Auth\Register\RegisterRequest $request): JsonResponse
    {
        

        if (!$this->validateTurnstile($request->input('turnstileToken'))) {
            return ApiResponse::error('Security verification failed. Please try again.', [
                'errors' => ['turnstileToken' => ['Security verification failed. Please try again.']],
            ], 422);
        }

        $userData = [
            'name' => trim($request->first_name . ' ' . $request->last_name),
            'first_name' => $request->first_name,
            'middle_name' => $request->middle_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ];

        $user = User::create($userData);

        // Assign default roles
        $user->assignRole(['Guest']);
        $user->load('roles');

        // Generate email verification token
        $verificationToken = $user->generateEmailVerificationToken();

        // Send verification email
        $user->notify(new EmailVerificationNotification($user, $verificationToken));

        return ApiResponse::success([
            'user' => [
                'id' => $user->id,
                'uuid' => $user->uuid,
                'name' => $user->name,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at,
                'onboarding_completed' => $user->onboarding_completed,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
                'roles' => $user->roles->pluck('name')->toArray(),
                'can_access_admin' => $user->hasAnyRole(['super-admin', 'admin']),
                'can_access_manage' => true,
            ],
            'requires_email_verification' => true,
        ], 'User registered successfully. Please verify your email address.', 201);
    }

    /**
     * Login user and return OAuth token
     */
    public function login(\App\Http\Requests\V1\Auth\Login\LoginRequest $request): JsonResponse
    {
        

        if (!$this->validateTurnstile($request->input('turnstileToken'))) {
            return ApiResponse::error('Security verification failed. Please try again.', [
                'errors' => ['turnstileToken' => ['Security verification failed. Please try again.']],
            ], 422);
        }

        $userByEmail = User::withTrashed()->where('email', $request->email)->first();
        if ($userByEmail && $userByEmail->trashed()) {
            return ApiResponse::error('Your account has been deactivated. Please contact support.', [
                'deactivated' => true,
            ], 403);
        }

        if (!Auth::attempt($request->only('email', 'password'))) {
            return ApiResponse::error('Invalid credentials', null, 401);
        }

        /** @var User $user */
        $user = Auth::user();

        if (!$user->isEmailVerified()) {
            Auth::logout();
            return ApiResponse::error('Please verify your email address before logging in.', [
                'requires_email_verification' => true,
                'email' => $user->email,
            ], 403);
        }

        // Load relationships for complete user data
        $user->load(['roles', 'avatars']);
        $tokenResult = $this->getOAuthToken($request->email, $request->password);

        if ($tokenResult['success']) {
            return ApiResponse::success([
                'user' => [
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
                    'onboarding_completed' => $user->onboarding_completed,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                    'roles' => $user->roles->pluck('name')->toArray(),
                    'can_access_admin' => $user->hasAnyRole(['super-admin', 'admin']),
                    'can_access_manage' => true,
                ],
                'access_token' => $tokenResult['access_token'],
                'refresh_token' => $tokenResult['refresh_token'],
                'expires_in' => $tokenResult['expires_in'],
                'token_type' => 'Bearer',
                'requires_onboarding' => !$user->isOnboardingComplete(),
            ], 'Login successful');
        }

        Log::error('OAuth token generation failed', [
            'email' => $request->email,
            'token_result' => $tokenResult,
        ]);

        return ApiResponse::error('Authentication failed.', null, 500);
    }

    /**
     * Logout user (revoke tokens)
     */
    public function logout(\App\Http\Requests\V1\Auth\Logout\LogoutRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        // Revoke all user's tokens
        $user->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Successfully logged out',
        ]);
    }

    /**
     * Refresh access token
     */
    public function refresh(\App\Http\Requests\V1\Auth\Refresh\RefreshRequest $request): JsonResponse
    {
        

        $tokenResult = $this->refreshOAuthToken($request->refresh_token);

        if ($tokenResult['success']) {
            return response()->json([
                'success' => true,
                'access_token' => $tokenResult['access_token'],
                'refresh_token' => $tokenResult['refresh_token'],
                'expires_in' => $tokenResult['expires_in'],
                'token_type' => 'Bearer',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Unable to refresh token',
        ], 401);
    }

    /**
     * Get OAuth token using password grant
     */
    private function getOAuthToken(string $email, string $password): array
    {
        try {
            // Authenticate user directly
            if (!Auth::attempt(['email' => $email, 'password' => $password])) {
                return ['success' => false, 'message' => 'Invalid credentials'];
            }

            /** @var User $user */
            $user = Auth::user();

            // Create token directly using Passport
            $tokenResult = $user->createToken('Personal Access Token');
            $token = $tokenResult->token;
            $token->expires_at = now()->addDays(15);
            $token->save();

            return [
                'success' => true,
                'access_token' => $tokenResult->accessToken,
                'refresh_token' => null, // Personal access tokens don't have refresh tokens
                'expires_in' => 15 * 24 * 60 * 60, // 15 days in seconds
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Refresh OAuth token (simplified for personal access tokens)
     */
    private function refreshOAuthToken(string $refreshToken): array
    {
        // For personal access tokens, we don't support refresh
        // In a production environment, you'd implement proper OAuth2 refresh flow
        return ['success' => false, 'message' => 'Refresh not supported for personal access tokens'];
    }

    /**
     * Check if an email has an account
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function checkAccount(\App\Http\Requests\V1\Auth\CheckAccountRequest $request): JsonResponse
    {
        

        if (!$this->validateTurnstile($request->input('turnstileToken'))) {
            return response()->json([
                'success' => false,
                'message' => 'Security verification failed. Please try again.',
                'errors' => [
                    'turnstileToken' => ['Security verification failed. Please try again.'],
                ],
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if ($user) {
            return response()->json([
                'success' => true,
                'exists' => true,
                'message' => 'An account exists with this email address.',
                'name' => $user->name,
                'registered_at' => $user->created_at->format('F Y'),
                'deactivated' => $user->trashed(),
            ], 200);
        }

        return response()->json([
            'success' => true,
            'exists' => false,
            'message' => 'No account found with this email address.'
        ], 200);
    }

    /**
     * Send password reset link
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function sendResetLink(Request $request): JsonResponse
    {
        

        if (!$this->validateTurnstile($request->input('turnstileToken'))) {
            return response()->json([
                'success' => false,
                'message' => 'Security verification failed. Please try again.',
                'errors' => [
                    'turnstileToken' => ['Security verification failed. Please try again.'],
                ],
            ], 422);
        }

        // Check if user exists
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'No account found with this email address.'
            ], 404);
        }

        try {
            // Delete any existing password reset tokens for this user
            // This prevents old queued emails from having invalid tokens
            DB::table('password_reset_tokens')
                ->where('email', $request->email)
                ->delete();

            Log::info('Cleared existing password reset tokens', [
                'email' => $request->email,
            ]);

            // Store the frontend context in session/cache for the notification
            $frontend = $request->input('frontend', 'manage'); // Default to manage
            cache()->put('password_reset_frontend_' . $user->id, $frontend, now()->addMinutes(10));

            Log::info('Password reset initiated', [
                'user_id' => $user->id,
                'email' => $request->email,
                'frontend' => $frontend,
                'cache_key' => 'password_reset_frontend_' . $user->id
            ]);

            // Send password reset link
            $status = Password::sendResetLink(
                $request->only('email')
            );

            Log::info('Password reset link sent', [
                'status' => $status,
                'user_id' => $user->id,
                'email' => $request->email
            ]);

            if ($status === Password::RESET_LINK_SENT) {
                return response()->json([
                    'success' => true,
                    'message' => 'Password reset link sent to your email address.'
                ], 200);
            }

            Log::error('Password reset link failed', ['status' => $status, 'email' => $request->email]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send password reset link. Please try again.'
            ], 500);
        } catch (\Exception $e) {
            Log::error('Password reset exception', ['error' => $e->getMessage(), 'email' => $request->email]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send password reset link: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reset password
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function resetPassword(Request $request): JsonResponse
    {
        

        // Get the plain token (it comes already decoded from the request body, not from URL)
        $plainToken = $request->token;

        Log::info('Password reset attempt', [
            'email' => $request->email,
            'token' => substr($plainToken, 0, 20) . '...',
            'token_length' => strlen($plainToken),
        ]);

        // Check if token exists in database
        $tokenRecord = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        Log::info('Token record from database', [
            'exists' => $tokenRecord ? 'yes' : 'no',
            'db_token' => $tokenRecord ? substr($tokenRecord->token, 0, 20) . '...' : 'N/A',
            'created_at' => $tokenRecord ? $tokenRecord->created_at : 'N/A',
        ]);

        // Verify token exists
        if (!$tokenRecord) {
            Log::warning('Password reset failed: no token found', ['email' => $request->email]);
            return response()->json([
                'success' => false,
                'message' => 'This password reset token is invalid.'
            ], 400);
        }

        // Verify token hasn't expired (60 minutes by default)
        $tokenExpiry = now()->subMinutes(config('auth.passwords.users.expire', 60));
        if ($tokenRecord->created_at < $tokenExpiry) {
            Log::warning('Password reset failed: token expired', [
                'email' => $request->email,
                'created_at' => $tokenRecord->created_at,
                'expiry_time' => $tokenExpiry
            ]);

            // Delete expired token
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();

            return response()->json([
                'success' => false,
                'message' => 'This password reset token has expired.'
            ], 400);
        }

        // Verify the plain token matches the hashed token in the database
        if (!Hash::check($plainToken, $tokenRecord->token)) {
            Log::warning('Password reset failed: token mismatch', [
                'email' => $request->email,
                'token_length' => strlen($plainToken),
                'hashed_token_length' => strlen($tokenRecord->token),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'This password reset token is invalid.'
            ], 400);
        }

        // Get the user
        $user = User::where('email', $request->email)->first();
        if (!$user) {
            Log::warning('Password reset failed: user not found', ['email' => $request->email]);
            return response()->json([
                'success' => false,
                'message' => 'User not found.'
            ], 404);
        }

        // Update password
        try {
            $user->forceFill([
                'password' => Hash::make($request->password)
            ])->save();

            Log::info('Password reset successful', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            // Delete the used token
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();

            return response()->json([
                'success' => true,
                'message' => 'Your password has been reset successfully.'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Password reset error', [
                'email' => $request->email,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to reset password. Please try again.'
            ], 500);
        }
    }

    /**
     * Verify email with token
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function verifyEmail(Request $request): JsonResponse
    {
        

        try {
            $user = User::where('email', $request->email)->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found.'
                ], 404);
            }

            // Check if already verified
            if ($user->isEmailVerified()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Email is already verified.'
                ], 200);
            }

            // Verify the token
            if ($user->verifyEmailWithToken($request->token)) {
                Log::info('Email verified successfully', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Email verified successfully.',
                ], 200);
            } else {
                Log::warning('Email verification failed: invalid token', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired verification token.'
                ], 400);
            }
        } catch (\Exception $e) {
            Log::error('Email verification error', [
                'error' => $e->getMessage(),
                'email' => $request->email,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to verify email. Please try again.'
            ], 500);
        }
    }

    /**
     * Resend verification email
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function resendVerificationEmail(Request $request): JsonResponse
    {
        

        if (!$this->validateTurnstile($request->input('turnstileToken'))) {
            return response()->json([
                'success' => false,
                'message' => 'Security verification failed. Please try again.',
                'errors' => [
                    'turnstileToken' => ['Security verification failed. Please try again.'],
                ],
            ], 422);
        }

        try {
            $user = User::where('email', $request->email)->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found.'
                ], 404);
            }

            // Check if already verified
            if ($user->isEmailVerified()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Email is already verified.'
                ], 200);
            }

            // Generate new token and send email
            $verificationToken = $user->generateEmailVerificationToken();
            $user->notify(new EmailVerificationNotification($user, $verificationToken));

            Log::info('Verification email resent', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Verification email sent. Please check your inbox.'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Resend verification email error', [
                'error' => $e->getMessage(),
                'email' => $request->email,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to resend verification email. Please try again.'
            ], 500);
        }
    }

    /**
     * Get authenticated user
     */
    public function user(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        // Load relationships
        $user->load('roles', 'avatars');

        // Get avatar URLs - will load relationship if not already loaded
        $avatarUrls = $user->getAvatarUrls();

        // Use new avatar URLs, fallback to old fields only if no avatar exists
        $mediumUrl = $avatarUrls['medium'];
        $thumbUrl = $avatarUrls['thumb'];
        $smallUrl = $avatarUrls['small'];

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at,
                'avatar_url' => $mediumUrl,
                'avatar_thumb' => $thumbUrl,
                'avatar_small' => $smallUrl,
                'avatar_medium' => $mediumUrl,
                'avatar_urls' => $avatarUrls,
                'onboarding_completed' => $user->onboarding_completed,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
                'roles' => $user->roles->pluck('name')->toArray(),
                'can_access_admin' => $user->hasAnyRole(['super-admin', 'admin']),
                'can_access_manage' => true,
            ],
        ], 200);
    }

    /**
     * Upload user avatar
     */
    public function uploadAvatar(Request $request): JsonResponse
    {
        

        /** @var User $user */
        $user = Auth::user();

        try {
            // Store S3 key in avatar_url field
            // In production, you would generate pre-signed URLs when serving
            $user->update([
                'avatar_url' => $request->input('avatar_s3_key'),
                'avatar_thumb' => $request->input('avatar_s3_key'),
                'avatar_small' => $request->input('avatar_s3_key'),
                'avatar_medium' => $request->input('avatar_s3_key'),
            ]);

            $user->load('roles');

            return response()->json([
                'success' => true,
                'message' => 'Avatar uploaded successfully',
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'email_verified_at' => $user->email_verified_at,
                    'avatar_url' => $user->avatar_url,
                    'avatar_thumb' => $user->avatar_thumb,
                    'avatar_small' => $user->avatar_small,
                    'avatar_medium' => $user->avatar_medium,
                    'onboarding_completed' => $user->onboarding_completed,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                    'roles' => $user->roles->pluck('name')->toArray(),
                    'can_access_admin' => $user->hasAnyRole(['super-admin', 'admin']),
                    'can_access_manage' => true,
                ],
            ], 200);
        } catch (\Exception $e) {
            Log::error('Avatar upload failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload avatar',
            ], 500);
        }
    }

    /**
     * Delete user avatar
     */
    public function deleteAvatar(): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        try {
            // Delete avatar file if exists
            if ($user->avatar_url) {
                $oldPath = str_replace('/storage/', '', parse_url($user->avatar_url, PHP_URL_PATH));
                if (Storage::disk('public')->exists($oldPath)) {
                    Storage::disk('public')->delete($oldPath);
                }
            }

            // Delete thumbnails
            foreach (['avatar_thumb', 'avatar_small', 'avatar_medium'] as $field) {
                if ($user->$field) {
                    $oldPath = str_replace('/storage/', '', parse_url($user->$field, PHP_URL_PATH));
                    if (Storage::disk('public')->exists($oldPath)) {
                        Storage::disk('public')->delete($oldPath);
                    }
                }
            }

            $user->update([
                'avatar_url' => null,
                'avatar_thumb' => null,
                'avatar_small' => null,
                'avatar_medium' => null,
            ]);

            $user->load('roles');

            return response()->json([
                'success' => true,
                'message' => 'Avatar deleted successfully',
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'email_verified_at' => $user->email_verified_at,
                    'avatar_url' => $user->avatar_url,
                    'avatar_thumb' => $user->avatar_thumb,
                    'avatar_small' => $user->avatar_small,
                    'avatar_medium' => $user->avatar_medium,
                    'onboarding_completed' => $user->onboarding_completed,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                    'roles' => $user->roles->pluck('name')->toArray(),
                    'can_access_admin' => $user->hasAnyRole(['super-admin', 'admin']),
                    'can_access_manage' => true,
                ],
            ], 200);
        } catch (\Exception $e) {
            Log::error('Avatar deletion failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete avatar',
            ], 500);
        }
    }

    /**
     * Validate Turnstile token (skip in local environment)
     */
    private function validateTurnstile(?string $token): bool
    {
        // Skip validation in local environment
        if (app()->environment('local', 'testing')) {
            return true;
        }

        // If no token provided in production, fail
        if (empty($token)) {
            return false;
        }

        // Validate with Cloudflare API
        try {
            $secretKey = config('services.turnstile.secret_key');

            if (empty($secretKey)) {
                Log::error('Turnstile validation failed: secret key is not configured');
                return false;
            }

            /** @var \Illuminate\Http\Client\Response $response */
            $response = \Illuminate\Support\Facades\Http::asForm()->timeout(10)->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'secret' => $secretKey,
                'response' => $token,
                'remoteip' => request()->ip(),
            ]);

            $result = $response->json();

            if (!$response->successful()) {
                Log::warning('Turnstile verification HTTP failure', [
                    'status' => $response->status(),
                    'response' => $result,
                ]);
                return false;
            }

            if (!isset($result['success']) || $result['success'] !== true) {
                Log::info('Turnstile verification rejected token', [
                    'error_codes' => $result['error-codes'] ?? [],
                ]);
            }

            return isset($result['success']) && $result['success'] === true;
        } catch (\Exception $e) {
            Log::error('Turnstile validation failed: ' . $e->getMessage());
            return false;
        }
    }
}
