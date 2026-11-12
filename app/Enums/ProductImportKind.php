<?php

namespace App\Enums;

/**
 * What a single import file contains.
 *
 * The three kinds share the same staging pipeline, so one upload endpoint and
 * one error report serve all of them. A catalogue is still built up in the
 * natural order: products first, then variants and images for those products.
 */
class ProductImportKind
{
    public const PRODUCTS = 'products';

    public const VARIANTS = 'variants';

    public const IMAGES = 'images';

    public const ALL = [
        self::PRODUCTS,
        self::VARIANTS,
        self::IMAGES,
    ];

    public static function label(string $kind): string
    {
        return match ($kind) {
            self::PRODUCTS => 'Products',
            self::VARIANTS => 'Variants',
            self::IMAGES => 'Images',
            default => ucfirst($kind),
        };
    }
}
