<?php

declare(strict_types=1);

return [

    'enabled' => (bool) env('ANALYTICS_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Buffer
    |--------------------------------------------------------------------------
    |
    | `redis` is the production buffer. `array` is used by the test suite so
    | feature tests never require a live Redis instance.
    |
    */

    'buffer' => env('ANALYTICS_BUFFER', 'redis'),

    'queue' => env('ANALYTICS_QUEUE', 'analytics'),

    /*
    |--------------------------------------------------------------------------
    | De-duplication
    |--------------------------------------------------------------------------
    */

    'dedupe_minutes' => (int) env('ANALYTICS_VIEW_DEDUPE_MINUTES', 30),
    'search_suggest_dedupe_minutes' => (int) env('ANALYTICS_SEARCH_SUGGEST_DEDUPE_MINUTES', 10),
    'search_results_dedupe_seconds' => (int) env('ANALYTICS_SEARCH_RESULTS_DEDUPE_SECONDS', 60),
    'purchase_dedupe_days' => (int) env('ANALYTICS_PURCHASE_DEDUPE_DAYS', 7),

    /*
    |--------------------------------------------------------------------------
    | Flush
    |--------------------------------------------------------------------------
    */

    'flush_lock_seconds' => (int) env('ANALYTICS_FLUSH_LOCK_SECONDS', 55),
    'flush_chunk_size' => (int) env('ANALYTICS_FLUSH_CHUNK_SIZE', 500),

    /*
    |--------------------------------------------------------------------------
    | Raw event stream (optional)
    |--------------------------------------------------------------------------
    |
    | When enabled, every accepted event is also appended to a Redis Stream for
    | a future consumer (or for replay into a warehouse). Loki remains the
    | searchable raw-event store; this stream is for durability/replay.
    |
    */

    'emit_stream' => (bool) env('ANALYTICS_EMIT_STREAM', false),
    'stream_key' => env('ANALYTICS_STREAM_KEY', 'analytics:stream:events'),
    'stream_max_length' => (int) env('ANALYTICS_STREAM_MAX_LENGTH', 100000),

    'log_bots' => (bool) env('ANALYTICS_LOG_BOTS', false),

    /*
    |--------------------------------------------------------------------------
    | Beacon endpoint
    |--------------------------------------------------------------------------
    */

    'beacon' => [
        'enabled' => (bool) env('ANALYTICS_BEACON_ENABLED', true),
        'max_batch' => (int) env('ANALYTICS_BEACON_MAX_BATCH', 20),
        'max_payload_bytes' => (int) env('ANALYTICS_BEACON_MAX_PAYLOAD_BYTES', 65536),
        'throttle' => env('ANALYTICS_BEACON_THROTTLE', '120,1'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Lifetime product counters
    |--------------------------------------------------------------------------
    |
    | Event metric => `products` column. Only these metrics are mirrored into
    | the hot product row; everything else lives in `analytics_daily`.
    |
    */

    'counters' => [
        'view' => 'view_count',
        'add_to_cart' => 'add_to_cart_count',
        'purchase' => 'purchase_count',
    ],

];
