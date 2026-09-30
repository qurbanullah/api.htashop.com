<?php

namespace App\Http\Controllers\V1\Auth;

use App\Enums\RefreshTokenFailureEnum;
use App\Enums\RefreshTokenRevokedReasonEnum;
use App\Exceptions\RefreshTokenException;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Auth\CheckAccountRequest;
use App\Http\Requests\V1\Auth\Login\LoginRequest;
use App\Http\Requests\V1\Auth\Logout\LogoutRequest;
use App\Http\Requests\V1\Auth\Refresh\RefreshRequest;
use App\Http\Requests\V1\Auth\Register\RegisterRequest;
use App\Http\Responses\V1\ApiResponse;
use App\Models\User;
use App\Notifications\EmailVerificationNotification;
use App\Services\Auth\RefreshTokenService;
use Illuminate\Http\Client\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Cookie;

class AuthController extends Controller
{
    /**
     * Register a new user
     */
    public function register(RegisterRequest $request): JsonResponse
    {

        if (! $this->validateTurnstile($request->input('turnstileToken'))) {
            return ApiResponse::error('Security verification failed. Please try again.', [
                'errors' => ['turnstileToken' => ['Security verification failed. Please try again.']],
            ], 422);
        }

        $userData = [
            'name' => trim($request->first_name.' '.$request->last_name),
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
    public function login(LoginRequest $request, RefreshTokenService $refreshTokenService): JsonResponse
    {

        if (! $this->validateTurnstile($request->input('turnstileToken'))) {
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

        if (! Auth::attempt($request->only('email', 'password'))) {
            return ApiResponse::error('Invalid credentials', null, 401);
        }

        /** @var User $user */
        $user = Auth::user();

        if (! $user->isEmailVerified()) {
            Auth::logout();

            return ApiResponse::error('Please verify your email address before logging in.', [
                'requires_email_verification' => true,
                'email' => $user->email,
            ], 403);
        }

        // Load relationships for complete user data
        $user->load(['roles', 'avatars']);

        $session = $refreshTokenService->startSession($user, $request);

        $payload = [
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
            'access_token' => $session['access_token'],
            'refresh_token' => $session['refresh_token'],
            'expires_in' => $session['expires_in'],
            'refresh_expires_in' => $this->secondsUntil($session['refresh_expires_at']),
            'token_type' => 'Bearer',
            'requires_onboarding' => ! $user->isOnboardingComplete(),
        ];

        // The storefront authenticates with the httpOnly cookies set below, so it
        // must not also receive a JS-readable copy of either token. Other clients
        // (admin/manage/native) still read them from the body as before.
        $isStorefront = $this->clientIsStorefront($request);

        if ($isStorefront) {
            unset($payload['access_token'], $payload['refresh_token']);
        }

        $response = ApiResponse::success($payload, 'Login successful');

        if ($isStorefront) {
            $response = $response
                ->withCookie($this->accessTokenCookie($session['access_token']))
                ->withCookie(
                    $this->refreshTokenCookie($session['refresh_token'], $session['refresh_expires_at'])
                );
        }

        Log::info('User signed in', [
            'user_id' => $user->id,
            'client' => $isStorefront ? 'storefront' : 'api',
        ]);

        return $response;
    }

    /**
     * Logout user (end this device's session)
     */
    public function logout(
        LogoutRequest $request,
        RefreshTokenService $refreshTokenService,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        // Revoke only the token behind this request. Deleting every token would
        // sign the same account out of its other devices, which is not what
        // "sign out" means on one — the native apps hold a token per device.
        // `TransientToken` (pure session auth) has no revoke() and nothing to
        // revoke; Passport's bearer `AccessToken` and the Eloquent `Token` both do.
        $token = $user->token();
        $accessTokenId = isset($token->id) ? (string) $token->id : null;

        if ($token && method_exists($token, 'revoke')) {
            $token->revoke();
        }

        // End the refresh lineage bound to this access token too. Without this,
        // "sign out" would leave a refresh token that could mint a new session
        // for the rest of its lifetime. Matching on the access token means we do
        // not need the refresh cookie to be sent to this endpoint.
        if ($accessTokenId !== null) {
            $refreshTokenService->revokeSessionForAccessToken(
                $accessTokenId,
                RefreshTokenRevokedReasonEnum::LOGOUT
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Successfully logged out',
        ])
            ->withCookie($this->forgetAccessTokenCookie())
            ->withCookie($this->forgetRefreshTokenCookie());
    }

    /**
     * Exchange a refresh token for a new access token.
     *
     * Refresh tokens are single-use: each call rotates to a successor, and
     * replaying a superseded token is treated as a compromise (see
     * RefreshTokenService). The storefront presents its token in an httpOnly
     * cookie; other clients send X-Refresh-Token or `refresh_token`.
     */
    public function refresh(
        RefreshRequest $request,
        RefreshTokenService $refreshTokenService,
    ): JsonResponse {
        $presented = $this->refreshTokenFromRequest($request);

        if ($presented === null) {
            return $this->refreshFailed(RefreshTokenFailureEnum::MISSING);
        }

        $isStorefront = $this->clientIsStorefront($request);

        try {
            $session = $refreshTokenService->rotate($presented, $request);
        } catch (RefreshTokenException $exception) {
            Log::info('Refresh token rejected', [
                'failure' => $exception->failure->value,
                'ip' => $request->ip(),
            ]);

            return $this->refreshFailed($exception->failure);
        }

        $payload = [
            'access_token' => $session['access_token'],
            'refresh_token' => $session['refresh_token'],
            'expires_in' => $session['expires_in'],
            'refresh_expires_in' => $this->secondsUntil($session['refresh_expires_at']),
            'token_type' => 'Bearer',
        ];

        if ($isStorefront) {
            unset($payload['access_token'], $payload['refresh_token']);
        }

        $response = ApiResponse::success($payload, 'Token refreshed');

        if ($isStorefront) {
            $response = $response
                ->withCookie($this->accessTokenCookie($session['access_token']))
                ->withCookie(
                    $this->refreshTokenCookie($session['refresh_token'], $session['refresh_expires_at'])
                );
        }

        return $response;
    }

    private function refreshFailed(RefreshTokenFailureEnum $failure): JsonResponse
    {
        // Clear the cookies so a browser does not keep replaying a dead session.
        return ApiResponse::error($failure->message(), ['code' => $failure->value], 401)
            ->withCookie($this->forgetAccessTokenCookie())
            ->withCookie($this->forgetRefreshTokenCookie());
    }

    /**
     * Where the client put its refresh token.
     */
    private function refreshTokenFromRequest(Request $request): ?string
    {
        $candidates = [
            (string) $request->header('X-Refresh-Token', ''),
            (string) $request->input('refresh_token', ''),
            (string) $request->cookie((string) config('auth_tokens.cookie.refresh'), ''),
        ];

        foreach ($candidates as $candidate) {
            $candidate = trim($candidate);

            if ($candidate !== '') {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Whether the caller is the storefront SPA (which holds its session in
     * httpOnly cookies) rather than the admin/manage/native clients.
     */
    private function clientIsStorefront(Request $request): bool
    {
        return strtolower((string) $request->header('X-Client', '')) === 'storefront';
    }

    /**
     * Issue a short-lived Passport access token.
     *
     * @return array{token: string, id: string|null, expires_in: int}
     */
    private function secondsUntil(\DateTimeInterface $moment): int
    {
        return max(0, $moment->getTimestamp() - now()->getTimestamp());
    }

    /**
     * Check if an email has an account
     *
     * @param  Request  $request
     */
    public function checkAccount(CheckAccountRequest $request): JsonResponse
    {

        if (! $this->validateTurnstile($request->input('turnstileToken'))) {
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
            'message' => 'No account found with this email address.',
        ], 200);
    }

    /**
     * Send password reset link
     */
    public function sendResetLink(Request $request): JsonResponse
    {

        if (! $this->validateTurnstile($request->input('turnstileToken'))) {
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

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'No account found with this email address.',
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
            cache()->put('password_reset_frontend_'.$user->id, $frontend, now()->addMinutes(10));

            Log::info('Password reset initiated', [
                'user_id' => $user->id,
                'email' => $request->email,
                'frontend' => $frontend,
                'cache_key' => 'password_reset_frontend_'.$user->id,
            ]);

            // Send password reset link
            $status = Password::sendResetLink(
                $request->only('email')
            );

            Log::info('Password reset link sent', [
                'status' => $status,
                'user_id' => $user->id,
                'email' => $request->email,
            ]);

            if ($status === Password::RESET_LINK_SENT) {
                return response()->json([
                    'success' => true,
                    'message' => 'Password reset link sent to your email address.',
                ], 200);
            }

            Log::error('Password reset link failed', ['status' => $status, 'email' => $request->email]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send password reset link. Please try again.',
            ], 500);
        } catch (\Exception $e) {
            Log::error('Password reset exception', ['error' => $e->getMessage(), 'email' => $request->email]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send password reset link: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reset password
     */
    public function resetPassword(Request $request): JsonResponse
    {

        // Get the plain token (it comes already decoded from the request body, not from URL)
        $plainToken = $request->token;

        Log::info('Password reset attempt', [
            'email' => $request->email,
            'token' => substr($plainToken, 0, 20).'...',
            'token_length' => strlen($plainToken),
        ]);

        // Check if token exists in database
        $tokenRecord = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        Log::info('Token record from database', [
            'exists' => $tokenRecord ? 'yes' : 'no',
            'db_token' => $tokenRecord ? substr($tokenRecord->token, 0, 20).'...' : 'N/A',
            'created_at' => $tokenRecord ? $tokenRecord->created_at : 'N/A',
        ]);

        // Verify token exists
        if (! $tokenRecord) {
            Log::warning('Password reset failed: no token found', ['email' => $request->email]);

            return response()->json([
                'success' => false,
                'message' => 'This password reset token is invalid.',
            ], 400);
        }

        // Verify token hasn't expired (60 minutes by default)
        $tokenExpiry = now()->subMinutes(config('auth.passwords.users.expire', 60));
        if ($tokenRecord->created_at < $tokenExpiry) {
            Log::warning('Password reset failed: token expired', [
                'email' => $request->email,
                'created_at' => $tokenRecord->created_at,
                'expiry_time' => $tokenExpiry,
            ]);

            // Delete expired token
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();

            return response()->json([
                'success' => false,
                'message' => 'This password reset token has expired.',
            ], 400);
        }

        // Verify the plain token matches the hashed token in the database
        if (! Hash::check($plainToken, $tokenRecord->token)) {
            Log::warning('Password reset failed: token mismatch', [
                'email' => $request->email,
                'token_length' => strlen($plainToken),
                'hashed_token_length' => strlen($tokenRecord->token),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'This password reset token is invalid.',
            ], 400);
        }

        // Get the user
        $user = User::where('email', $request->email)->first();
        if (! $user) {
            Log::warning('Password reset failed: user not found', ['email' => $request->email]);

            return response()->json([
                'success' => false,
                'message' => 'User not found.',
            ], 404);
        }

        // Update password
        try {
            $user->forceFill([
                'password' => Hash::make($request->password),
            ])->save();

            Log::info('Password reset successful', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            // Delete the used token
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();

            return response()->json([
                'success' => true,
                'message' => 'Your password has been reset successfully.',
            ], 200);
        } catch (\Exception $e) {
            Log::error('Password reset error', [
                'email' => $request->email,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to reset password. Please try again.',
            ], 500);
        }
    }

    /**
     * Verify email with token
     */
    public function verifyEmail(Request $request): JsonResponse
    {

        try {
            $user = User::where('email', $request->email)->first();

            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found.',
                ], 404);
            }

            // Check if already verified
            if ($user->isEmailVerified()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Email is already verified.',
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
                    'message' => 'Invalid or expired verification token.',
                ], 400);
            }
        } catch (\Exception $e) {
            Log::error('Email verification error', [
                'error' => $e->getMessage(),
                'email' => $request->email,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to verify email. Please try again.',
            ], 500);
        }
    }

    /**
     * Resend verification email
     */
    public function resendVerificationEmail(Request $request): JsonResponse
    {

        if (! $this->validateTurnstile($request->input('turnstileToken'))) {
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

            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found.',
                ], 404);
            }

            // Check if already verified
            if ($user->isEmailVerified()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Email is already verified.',
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
                'message' => 'Verification email sent. Please check your inbox.',
            ], 200);
        } catch (\Exception $e) {
            Log::error('Resend verification email error', [
                'error' => $e->getMessage(),
                'email' => $request->email,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to resend verification email. Please try again.',
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

        if (! $user) {
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

        $payload = [
            'id' => $user->id,
            'uuid' => $user->uuid,
            'name' => $user->name,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
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
        ];

        // The account belongs under `data` so every client can read it through
        // the shared ApiResponse envelope (they all parse `data`). The top-level
        // `user` key is kept for older clients and can be dropped once none of
        // them read it.
        return response()->json([
            'success' => true,
            'message' => 'Authenticated user retrieved successfully.',
            'data' => $payload,
            'user' => $payload,
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
            Log::error('Avatar upload failed: '.$e->getMessage());

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
            Log::error('Avatar deletion failed: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete avatar',
            ], 500);
        }
    }

    /**
     * Build the httpOnly access-token cookie used by the storefront so the
     * Bearer token never lives in localStorage (and can't be read by injected
     * scripts). Admin/manage continue to read the token from the JSON body.
     *
     * Its lifetime matches the (short) access-token TTL — the refresh cookie is
     * what keeps the session alive.
     */
    private function accessTokenCookie(string $token): Cookie
    {
        return cookie(
            (string) config('auth_tokens.cookie.access'),
            $token,
            (int) ceil(((int) config('auth_tokens.access_ttl')) / 60), // minutes
            '/',
            null, // host-only (api.htashop.com)
            ! app()->environment('local', 'testing'), // Secure in production
            true, // httpOnly
            false, // raw
            'lax' // htashop.com <-> api.htashop.com are same-site
        );
    }

    private function forgetAccessTokenCookie(): Cookie
    {
        return cookie(
            (string) config('auth_tokens.cookie.access'),
            '',
            -2628000, // expired — instructs the browser to delete it
            '/',
            null,
            ! app()->environment('local', 'testing'),
            true,
            false,
            'lax'
        );
    }

    /**
     * The refresh cookie.
     *
     * Path-scoped to the refresh endpoint, so the longest-lived credential is not
     * attached to any other request — not even the rest of the API. Sign-out does
     * not need it: it revokes the family recorded against the access token.
     */
    private function refreshTokenCookie(
        string $token,
        \DateTimeInterface $expiresAt,
    ): Cookie {
        return cookie(
            (string) config('auth_tokens.cookie.refresh'),
            $token,
            // Laravel's cookie helper takes minutes, not an absolute date.
            (int) max(1, ceil($this->secondsUntil($expiresAt) / 60)),
            (string) config('auth_tokens.cookie.refresh_path'),
            null,
            ! app()->environment('local', 'testing'),
            true,
            false,
            'lax'
        );
    }

    private function forgetRefreshTokenCookie(): Cookie
    {
        return cookie(
            (string) config('auth_tokens.cookie.refresh'),
            '',
            -2628000,
            (string) config('auth_tokens.cookie.refresh_path'),
            null,
            ! app()->environment('local', 'testing'),
            true,
            false,
            'lax'
        );
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

            /** @var Response $response */
            $response = Http::asForm()->timeout(10)->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'secret' => $secretKey,
                'response' => $token,
                'remoteip' => request()->ip(),
            ]);

            $result = $response->json();

            if (! $response->successful()) {
                Log::warning('Turnstile verification HTTP failure', [
                    'status' => $response->status(),
                    'response' => $result,
                ]);

                return false;
            }

            if (! isset($result['success']) || $result['success'] !== true) {
                Log::info('Turnstile verification rejected token', [
                    'error_codes' => $result['error-codes'] ?? [],
                ]);
            }

            return isset($result['success']) && $result['success'] === true;
        } catch (\Exception $e) {
            Log::error('Turnstile validation failed: '.$e->getMessage());

            return false;
        }
    }
}
