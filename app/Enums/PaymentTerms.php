<?php

namespace App\Enums;

/**
 * Commercial payment terms offered on a quotation.
 */
enum PaymentTerms: string
{
    case ADVANCE_100 = 'advance_100';
    case ADVANCE_50 = 'advance_50';
    case NET_15 = 'net_15';
    case NET_30 = 'net_30';
    case NET_45 = 'net_45';
    case NET_60 = 'net_60';
    case LC_AT_SIGHT = 'lc_at_sight';
    case CASH_ON_DELIVERY = 'cash_on_delivery';

    public function label(): string
    {
        return match ($this) {
            self::ADVANCE_100 => '100% advance',
            self::ADVANCE_50 => '50% advance, 50% on dispatch',
            self::NET_15 => 'Net 15 days',
            self::NET_30 => 'Net 30 days',
            self::NET_45 => 'Net 45 days',
            self::NET_60 => 'Net 60 days',
            self::LC_AT_SIGHT => 'Letter of credit at sight',
            self::CASH_ON_DELIVERY => 'Cash on delivery',
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
