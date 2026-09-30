<?php

namespace App\Actions\Auth;

use App\Models\RefreshToken;
use DateTimeInterface;

/**
 * Deletes refresh tokens that can no longer be presented.
 *
 * Only rows past their absolute expiry are removed. A revoked-but-unexpired row
 * is deliberately kept for the rest of its lifetime: without it, replaying a
 * stolen token would look like an unknown token instead of triggering the
 * family revocation that theft detection depends on.
 */
class PruneRefreshTokensAction
{
    public function execute(DateTimeInterface $expiredBefore): int
    {
        return RefreshToken::query()
            ->where('expires_at', '<', $expiredBefore)
            ->delete();
    }
}
