<?php

namespace App\Enums;

class Sourcing
{
    public const IN_STOCK = 'in_stock';

    public const ON_DEMAND = 'on_demand';

    public const DROPSHIP = 'dropship';

    public const PREORDER = 'preorder';

    public const ALL = [
        self::IN_STOCK,
        self::ON_DEMAND,
        self::DROPSHIP,
        self::PREORDER,
    ];

    public static function label(string $sourcing): string
    {
        return match ($sourcing) {
            self::IN_STOCK => 'In Stock',
            self::ON_DEMAND => 'Import on Demand',
            self::DROPSHIP => 'Drop-ship',
            self::PREORDER => 'Pre-order',
            default => ucfirst($sourcing),
        };
    }

    /**
     * Sourcing modes that must be paid for before the goods are ordered from the
     * supplier. Cash on delivery is not viable for these: the merchant carries
     * the lead time and freight with no customer commitment, so a refusal at the
     * door loses the whole order.
     */
    public static function requiresAdvancePayment(string $sourcing): bool
    {
        return $sourcing !== self::IN_STOCK;
    }
}
