<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Storefront Catalog
    |--------------------------------------------------------------------------
    */
    'top_nav' => [
        // Maximum number of root categories shown in the storefront top nav.
        // `is_featured` marks the curated pool (see CategoryTopNavSeeder); this
        // limit caps how many are served, ordered by `featured_rank`.
        'limit' => (int) env('CATALOG_TOP_NAV_LIMIT', 6),
    ],
];
