<?php

namespace App\Enums;

/**
 * Lifecycle of one quote response revision.
 */
class QuoteResponseStatus
{
    public const DRAFT = 'draft';

    public const SENT = 'sent';

    public const VIEWED = 'viewed';

    public const ACCEPTED = 'accepted';

    public const DECLINED = 'declined';

    public const EXPIRED = 'expired';

    public const SUPERSEDED = 'superseded';

    public const ALL = [
        self::DRAFT,
        self::SENT,
        self::VIEWED,
        self::ACCEPTED,
        self::DECLINED,
        self::EXPIRED,
        self::SUPERSEDED,
    ];

    public static function label(string $status): string
    {
        return match ($status) {
            self::DRAFT => 'Draft',
            self::SENT => 'Sent',
            self::VIEWED => 'Viewed',
            self::ACCEPTED => 'Accepted',
            self::DECLINED => 'Declined',
            self::EXPIRED => 'Expired',
            self::SUPERSEDED => 'Superseded',
            default => ucfirst($status),
        };
    }
}
