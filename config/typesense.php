<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Typesense Search Engine
    |--------------------------------------------------------------------------
    |
    | Typesense is used for instant, typo-tolerant product suggestions and
    | full-text search. All cache logic lives in the service layer (see
    | App\Services\Search\ProductSearchService).
    |
    */

    'enabled' => (bool) env('TYPESENSE_ENABLED', false),

    // Bare hostname (no scheme — the protocol is configured separately below).
    'host' => env('TYPESENSE_HOST', 'localhost'),

    'port' => (int) env('TYPESENSE_PORT', 8108),

    'protocol' => env('TYPESENSE_PROTOCOL', 'http'),

    'api_key' => env('TYPESENSE_API_KEY', ''),

    'connect_timeout_seconds' => (float) env('TYPESENSE_CONNECT_TIMEOUT_SECONDS', 3),

    'healthcheck_interval_seconds' => (int) env('TYPESENSE_HEALTHCHECK_INTERVAL_SECONDS', 60),

    'products_collection' => env('TYPESENSE_PRODUCTS_COLLECTION', 'products'),

    // Maximum number of product suggestions returned by /search/suggest.
    'suggest_limit' => (int) env('TYPESENSE_SUGGEST_LIMIT', 8),

    // How long suggestion responses stay in the Redis cache (seconds).
    'suggest_cache_ttl' => (int) env('TYPESENSE_SUGGEST_CACHE_TTL', 300),
];
