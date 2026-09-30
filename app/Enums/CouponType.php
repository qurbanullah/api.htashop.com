<?php

namespace App\Enums;

class CouponType
{
    /** A fixed amount off the subtotal, in the order currency. */
    public const FIXED = 'fixed';

    /** A percentage off the subtotal, optionally capped. */
    public const PERCENT = 'percent';

    public const ALL = [
        self::FIXED,
        self::PERCENT,
    ];

    public static function values(): array
    {
        return self::ALL;
    }

    public static function label(string $type): string
    {
        return match ($type) {
            self::FIXED => 'Fixed amount',
            self::PERCENT => 'Percentage',
            default => ucfirst($type),
        };
    }

    public static function color(string $type): string
    {
        return match ($type) {
            self::FIXED => 'blue',
            self::PERCENT => 'purple',
            default => 'gray',
        };
    }
}
