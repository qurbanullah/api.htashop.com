<?php

namespace App\Enums;

class BannerLinkType
{
    public const NONE = 'none';
    public const PRODUCT = 'product';
    public const CATEGORY = 'category';
    public const BRAND = 'brand';
    public const SEARCH = 'search';
    public const EXTERNAL = 'external';

    public const ALL = [
        self::NONE,
        self::PRODUCT,
        self::CATEGORY,
        self::BRAND,
        self::SEARCH,
        self::EXTERNAL,
    ];
}
