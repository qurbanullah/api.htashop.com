<?php

namespace App\Enums;

class BannerType
{
    public const HERO = 'hero';
    public const PROMO = 'promo';
    public const SPONSORED = 'sponsored';
    public const TOP_BRANDS = 'top_brands';
    public const JUST_LAUNCHED = 'just_launched';
    public const SPLIT = 'split';
    public const SINGLE = 'single';

    public const ALL = [
        self::HERO,
        self::PROMO,
        self::SPONSORED,
        self::TOP_BRANDS,
        self::JUST_LAUNCHED,
        self::SPLIT,
        self::SINGLE,
    ];

    public static function label(string $type): string
    {
        return match ($type) {
            self::HERO => 'Hero carousel',
            self::PROMO => 'Promo banner',
            self::SPONSORED => 'Sponsored',
            self::TOP_BRANDS => 'Top brands',
            self::JUST_LAUNCHED => 'Just launched',
            self::SPLIT => 'Split grid',
            self::SINGLE => 'Single banner',
            default => ucfirst(str_replace('_', ' ', $type)),
        };
    }
}
