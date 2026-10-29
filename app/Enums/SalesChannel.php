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

    public static function description(string $code): string
    {
        return match ($code) {
            self::STOREFRONT => 'Publish the product in the public consumer storefront catalog.',
            self::PUNCHOUT => 'Expose the product to connected procurement systems through punchout catalogs.',
            self::MARKETPLACE => 'List the product on third-party marketplaces.',
            self::API => 'Make the product available to partner integrations reading the catalog over the API.',
            default => 'Additional sales channel.',
        };
    }
}
