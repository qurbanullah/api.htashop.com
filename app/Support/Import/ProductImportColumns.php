<?php

namespace App\Support\Import;

/**
 * The public import contract. Every column here is documented in
 * `docs/products/templates/README.md`; the template generator and the parser
 * both read this list so they cannot drift apart.
 */
class ProductImportColumns
{
    public const PRODUCTS = [
        'external_id',
        'action',
        'name',
        'slug',
        'category_path',
        'brand',
        'manufacturer',
        'summary',
        'description_html',
        'highlights',
        'features',
        'specs',
        'tags',
        'status',
        'is_active',
        'part_number',
        'model_number',
        'gtin',
        'barcode',
        'hs_code',
        'unspsc',
        'ntn',
        'currency',
        'price',
        'sale_price',
        'cost_price',
        'unit',
        'stock_qty',
        'warehouse_code',
        'low_stock_threshold',
        'weight_kg',
        'length_cm',
        'width_cm',
        'height_cm',
        'ships_alone',
        'is_stackable',
        'is_fragile',
        'is_hazmat',
        'hazard_class',
        'whats_included',
        'packaging_notes',
        'image_urls',
        'video_urls',
        'seo_title',
        'seo_description',
        'channel_codes',
    ];

    public const VARIANTS = [
        'parent_external_id',
        'external_id',
        'action',
        'name',
        'price',
        'sale_price',
        'cost_price',
        'stock_qty',
        'barcode',
        'gtin',
        'weight_kg',
        'length_cm',
        'width_cm',
        'height_cm',
        'option_1_name',
        'option_1_value',
        'option_2_name',
        'option_2_value',
        'option_3_name',
        'option_3_value',
    ];

    public const IMAGES = [
        'external_id',
        'image_url',
        'alt',
        'sort_order',
        'is_featured',
    ];
}
