<?php

namespace App\Enums;

final class SalesChannel
{
    public const STOREFRONT = 'storefront';

    public const PUNCHOUT = 'punchout';

    public const MARKETPLACE = 'marketplace';

    public const API = 'api';

    public const ALL = [
        self::STOREFRONT,
        self::PUNCHOUT,
        self::MARKETPLACE,
        self::API,
    ];

    public static function label(string $code): string
    {
        return match ($code) {
            self::STOREFRONT => 'Consumer storefront',
            self::PUNCHOUT => 'Punchout catalog',
            self::MARKETPLACE => 'Marketplace',
            self::API => 'API / partner',
            default => ucfirst($code),
        };
    }
}
