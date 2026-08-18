<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Storefront Catalog
    |--------------------------------------------------------------------------
    */
    'top_nav' => [
        // Maximum number of root categories shown in the storefront top nav.
        // Only categories flagged with metadata.show_top_category_nav = true
        // are considered, and this limit caps how many are returned.
        'limit' => (int) env('CATALOG_TOP_NAV_LIMIT', 8),
    ],
];
