<?php

namespace App\Actions\Auth;

use App\Models\RefreshToken;
use Illuminate\Support\Facades\Log;

/**
 * Persists one refresh-token row.
 *
 * The raw token is never passed here — only its hash — so a stray log or query
 * dump cannot leak a usable session.
 */
class CreateRefreshTokenAction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(array $attributes): RefreshToken
    {
        $refreshToken = RefreshToken::query()->create($attributes);

        Log::info('Refresh token issued', [
            'refresh_token_id' => $refreshToken->id,
            'user_id' => $refreshToken->user_id,
            'family_id' => $refreshToken->family_id,
            'expires_at' => $refreshToken->expires_at->toIso8601String(),
        ]);

        return $refreshToken;
    }
}
