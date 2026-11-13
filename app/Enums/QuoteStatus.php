<?php

namespace App\Enums;

/**
 * Sales workflow state for a quote request.
 *
 * `quoted` and `converted` are legacy values that were mapped to `replied` and
 * `accepted` respectively.
 */
class QuoteStatus
{
    public const PENDING = 'pending';

    public const IN_PROGRESS = 'in_progress';

    public const REPLIED = 'replied';

    public const ACCEPTED = 'accepted';

    public const DECLINED = 'declined';

    public const EXPIRED = 'expired';

    public const CANCELLED = 'cancelled';

    public const ALL = [
        self::PENDING,
        self::IN_PROGRESS,
        self::REPLIED,
        self::ACCEPTED,
        self::DECLINED,
        self::EXPIRED,
        self::CANCELLED,
    ];

    /** Statuses that still need sales attention. */
    public const OPEN = [
        self::PENDING,
        self::IN_PROGRESS,
        self::REPLIED,
    ];

    /**
     * Allowed transitions, excluding no-ops.
     *
     * @var array<string, array<int, string>>
     */
    private const TRANSITIONS = [
        self::PENDING => [self::IN_PROGRESS, self::REPLIED, self::CANCELLED],
        self::IN_PROGRESS => [self::REPLIED, self::CANCELLED],
        self::REPLIED => [
            self::IN_PROGRESS,
            self::ACCEPTED,
            self::DECLINED,
            self::EXPIRED,
            self::CANCELLED,
        ],
        self::ACCEPTED => [self::IN_PROGRESS],
        self::DECLINED => [self::IN_PROGRESS],
        self::EXPIRED => [self::IN_PROGRESS],
        self::CANCELLED => [self::IN_PROGRESS],
    ];

    public static function canTransition(string $from, string $to): bool
    {
        if ($from === $to) {
            return true;
        }

        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public static function label(string $status): string
    {
        return match ($status) {
            self::PENDING => 'Pending',
            self::IN_PROGRESS => 'In progress',
            self::REPLIED => 'Replied',
            self::ACCEPTED => 'Accepted',
            self::DECLINED => 'Declined',
            self::EXPIRED => 'Expired',
            self::CANCELLED => 'Cancelled',
            default => ucfirst(str_replace('_', ' ', $status)),
        };
    }
}
