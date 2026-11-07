<?php

namespace App\Enums;

/**
 * Lifecycle of one physical fulfilment: a set of order items packed into one or
 * more parcels and handed to a carrier.
 *
 * `pending` → `packing` → `packed` → `shipped` → `delivered`. Anything still
 * open may be cancelled; once shipped the record becomes an audit trail and is
 * no longer editable.
 */
class ShipmentStatus
{
    public const PENDING = 'pending';

    public const PACKING = 'packing';

    public const PACKED = 'packed';

    public const SHIPPED = 'shipped';

    public const DELIVERED = 'delivered';

    public const CANCELLED = 'cancelled';

    public const ALL = [
        self::PENDING,
        self::PACKING,
        self::PACKED,
        self::SHIPPED,
        self::DELIVERED,
        self::CANCELLED,
    ];

    /** States in which contents and parcels may still change. */
    public const OPEN = [
        self::PENDING,
        self::PACKING,
        self::PACKED,
    ];

    /**
     * @var array<string, array<int, string>>
     */
    public const TRANSITIONS = [
        self::PENDING => [self::PACKING, self::CANCELLED],
        self::PACKING => [self::PACKED, self::CANCELLED],
        self::PACKED => [self::SHIPPED, self::CANCELLED],
        self::SHIPPED => [self::DELIVERED],
        self::DELIVERED => [],
        self::CANCELLED => [],
    ];

    public static function label(string $status): string
    {
        return match ($status) {
            self::PENDING => 'Pending',
            self::PACKING => 'Packing',
            self::PACKED => 'Packed',
            self::SHIPPED => 'Shipped',
            self::DELIVERED => 'Delivered',
            self::CANCELLED => 'Cancelled',
            default => ucfirst($status),
        };
    }

    public static function isOpen(string $status): bool
    {
        return in_array($status, self::OPEN, true);
    }

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }
}
