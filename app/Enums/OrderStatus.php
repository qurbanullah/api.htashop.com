<?php

namespace App\Enums;

class OrderStatus
{
    public const PENDING = 'pending';

    public const CONFIRMED = 'confirmed';

    public const PROCESSING = 'processing';

    public const AWAITING_SOURCING = 'awaiting_sourcing';

    public const IN_TRANSIT = 'in_transit';

    public const IN_CUSTOMS = 'in_customs';

    public const ARRIVED_WAREHOUSE = 'arrived_warehouse';

    public const SHIPPED = 'shipped';

    public const DELIVERED = 'delivered';

    public const CANCELLED = 'cancelled';

    public const REFUNDED = 'refunded';

    public const ALL = [
        self::PENDING,
        self::CONFIRMED,
        self::PROCESSING,
        self::AWAITING_SOURCING,
        self::IN_TRANSIT,
        self::IN_CUSTOMS,
        self::ARRIVED_WAREHOUSE,
        self::SHIPPED,
        self::DELIVERED,
        self::CANCELLED,
        self::REFUNDED,
    ];

    /**
     * The import-on-demand progress trail. Orders that flow through these
     * states are waiting on the supplier/freight rather than domestic delivery.
     */
    public const IMPORT_LIFECYCLE = [
        self::AWAITING_SOURCING,
        self::IN_TRANSIT,
        self::IN_CUSTOMS,
        self::ARRIVED_WAREHOUSE,
    ];

    public static function label(string $status): string
    {
        return match ($status) {
            self::PENDING => 'Pending',
            self::CONFIRMED => 'Confirmed',
            self::PROCESSING => 'Processing',
            self::AWAITING_SOURCING => 'Awaiting Sourcing',
            self::IN_TRANSIT => 'In Transit',
            self::IN_CUSTOMS => 'In Customs',
            self::ARRIVED_WAREHOUSE => 'Arrived at Warehouse',
            self::SHIPPED => 'Shipped',
            self::DELIVERED => 'Delivered',
            self::CANCELLED => 'Cancelled',
            self::REFUNDED => 'Refunded',
            default => ucfirst($status),
        };
    }
}
