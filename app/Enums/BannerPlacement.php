<?php

namespace App\Enums;

class BannerPlacement
{
    public const HOME = 'home';
    public const CATEGORY = 'category';
    public const SEARCH = 'search';
    public const PRODUCT_DETAIL = 'product_detail';

    public const ALL = [
        self::HOME,
        self::CATEGORY,
        self::SEARCH,
        self::PRODUCT_DETAIL,
    ];

    public static function label(string $placement): string
    {
        return match ($placement) {
            self::HOME => 'Home page',
            self::CATEGORY => 'Category page',
            self::SEARCH => 'Search page',
            self::PRODUCT_DETAIL => 'Product detail page',
            default => ucfirst(str_replace('_', ' ', $placement)),
        };
    }
}
