<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Mints one short-lived Passport access token.
 *
 * The returned `id` is the token's `jti`, which is what binds it to the refresh
 * token issued alongside it — so revoking either retires both.
 */
class IssueAccessTokenAction
{
    /**
     * @return array{token: string, id: string|null, expires_in: int}
     */
    public function execute(User $user): array
    {
        $ttl = (int) config('auth_tokens.access_ttl');

        $tokenResult = $user->createToken('Personal Access Token');
        $token = $tokenResult->token;
        $token->expires_at = now()->addSeconds($ttl);
        $token->save();

        Log::info('Access token issued', [
            'user_id' => $user->id,
            'access_token_id' => $token->id,
            'expires_at' => $token->expires_at?->toIso8601String(),
        ]);

        return [
            'token' => $tokenResult->accessToken,
            'id' => $token->id !== null ? (string) $token->id : null,
            'expires_in' => $ttl,
        ];
    }
}
