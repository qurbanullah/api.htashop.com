<?php

namespace App\Enums;

/**
 * Why a refresh token row was revoked.
 *
 * Distinguishing ROTATED from the rest matters: a rotated token is expected to be
 * presented again only if the client never received its successor, whereas any
 * other reason means the session was deliberately ended.
 */
enum RefreshTokenRevokedReasonEnum: string
{
    /** Superseded by its successor after a successful refresh. */
    case ROTATED = 'rotated';

    /** The user signed out on this device. */
    case LOGOUT = 'logout';

    /** A superseded token was replayed while its successor had already been used. */
    case REUSE_DETECTED = 'reuse_detected';

    /** Replaced after a lost response, to unblock the client without a re-login. */
    case RECOVERED = 'recovered';

    /** Every session is ended when the account password changes. */
    case PASSWORD_CHANGED = 'password_changed';

    case ACCOUNT_DEACTIVATED = 'account_deactivated';

    case REVOKED_BY_ADMIN = 'revoked_by_admin';

    public function label(): string
    {
        return match ($this) {
            self::ROTATED => 'Rotated',
            self::LOGOUT => 'Signed out',
            self::REUSE_DETECTED => 'Reuse detected',
            self::RECOVERED => 'Recovered',
            self::PASSWORD_CHANGED => 'Password changed',
            self::ACCOUNT_DEACTIVATED => 'Account deactivated',
            self::REVOKED_BY_ADMIN => 'Revoked by admin',
        };
    }

    /**
     * Reasons that indicate the token may have leaked, as opposed to an expected
     * end of session. Used for alerting.
     *
     * @return array<int, self>
     */
    public static function compromiseIndicators(): array
    {
        return [self::REUSE_DETECTED];
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
