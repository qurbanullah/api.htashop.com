<?php

namespace App\Actions\Auth;

use Laravel\Passport\Passport;

/**
 * Retires the Passport access tokens issued alongside revoked refresh tokens.
 *
 * Without this, ending a session would leave its access token usable until its
 * TTL ran out — up to an hour of access after the user was signed out or a
 * compromise was detected.
 */
class RevokeAccessTokensAction
{
    /**
     * @param  array<int, string|null>  $tokenIds  Passport token ids (the JWT `jti`)
     */
    public function execute(array $tokenIds): int
    {
        $ids = array_values(array_unique(array_filter($tokenIds)));

        if ($ids === []) {
            return 0;
        }

        return Passport::token()
            ->newQuery()
            ->whereIn('id', $ids)
            ->where('revoked', false)
            ->update(['revoked' => true]);
    }
}
