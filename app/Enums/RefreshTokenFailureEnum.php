<?php

namespace App\Enums;

/**
 * Why a refresh attempt failed.
 *
 * The values double as the machine-readable `code` in the error envelope, so the
 * storefront can tell "refresh this session" apart from "you are signed out" and
 * react without parsing prose.
 */
enum RefreshTokenFailureEnum: string
{
    case MISSING = 'refresh_token_missing';
    case INVALID = 'refresh_token_invalid';
    case EXPIRED = 'refresh_token_expired';
    case IDLE_EXPIRED = 'refresh_token_idle_expired';
    case REVOKED = 'refresh_token_revoked';
    case REUSED = 'refresh_token_reused';

    public function message(): string
    {
        return match ($this) {
            self::MISSING => 'A refresh token is required.',
            self::INVALID => 'This session is no longer valid. Please sign in again.',
            self::EXPIRED => 'This session has expired. Please sign in again.',
            self::IDLE_EXPIRED => 'This session expired from inactivity. Please sign in again.',
            self::REVOKED => 'This session was ended. Please sign in again.',
            self::REUSED => 'This session was ended for security reasons. Please sign in again.',
        };
    }
}
