<?php

namespace App\Enums;

class BomRequestStatus
{
    public const PENDING = 'pending';

    public const IN_REVIEW = 'in_review';

    public const QUOTED = 'quoted';

    public const SOURCING = 'sourcing';

    public const FULFILLED = 'fulfilled';

    public const DECLINED = 'declined';

    public const CANCELLED = 'cancelled';

    public const ALL = [
        self::PENDING,
        self::IN_REVIEW,
        self::QUOTED,
        self::SOURCING,
        self::FULFILLED,
        self::DECLINED,
        self::CANCELLED,
    ];

    public static function label(string $status): string
    {
        return match ($status) {
            self::PENDING => 'Pending',
            self::IN_REVIEW => 'In Review',
            self::QUOTED => 'Quoted',
            self::SOURCING => 'Sourcing',
            self::FULFILLED => 'Fulfilled',
            self::DECLINED => 'Declined',
            self::CANCELLED => 'Cancelled',
            default => ucfirst($status),
        };
    }
}
