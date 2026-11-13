<?php

namespace App\Enums;

/**
 * How the goods leave the warehouse for this quotation.
 */
enum ShippingMethod: string
{
    case COURIER = 'courier';
    case AIR_FREIGHT = 'air_freight';
    case SEA_FREIGHT = 'sea_freight';
    case ROAD_FREIGHT = 'road_freight';
    case RAIL_FREIGHT = 'rail_freight';
    case LOCAL_DELIVERY = 'local_delivery';
    case CUSTOMER_PICKUP = 'customer_pickup';

    public function label(): string
    {
        return match ($this) {
            self::COURIER => 'Courier / parcel',
            self::AIR_FREIGHT => 'Air freight',
            self::SEA_FREIGHT => 'Sea freight',
            self::ROAD_FREIGHT => 'Road freight',
            self::RAIL_FREIGHT => 'Rail freight',
            self::LOCAL_DELIVERY => 'Local delivery',
            self::CUSTOMER_PICKUP => 'Customer pickup',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $case): array => [
            'value' => $case->value,
            'label' => $case->label(),
        ], self::cases());
    }

    public static function labelFor(?string $value): ?string
    {
        return $value === null ? null : self::tryFrom($value)?->label();
    }
}
