<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Service identity
    |--------------------------------------------------------------------------
    |
    | Used in structured logs and (later) OpenTelemetry resource attributes.
    |
    */

    'service' => env('OTEL_SERVICE_NAME', 'htashop-api'),

    'environment' => env('APP_ENV', 'production'),

    'analytics_channel' => env('ANALYTICS_LOG_CHANNEL', 'analytics'),

    /*
    |--------------------------------------------------------------------------
    | Audit trail
    |--------------------------------------------------------------------------
    |
    | The hot mirror lives in MariaDB. The daily exporter writes immutable
    | NDJSON + a manifest to object storage; pruning is only allowed for days
    | that have a completed export.
    |
    */

    'audit' => [
        'channel' => env('AUDIT_LOG_CHANNEL', 'audit'),
        'disk' => env('AUDIT_EXPORT_DISK', 'observability'),
        'prefix' => trim((string) env('AUDIT_EXPORT_PREFIX', 'audits'), '/'),
        'retention_days' => (int) env('AUDIT_RETENTION_DAYS', 365),
        'export_timezone' => env('AUDIT_EXPORT_TIMEZONE', 'UTC'),
        'chunk_size' => (int) env('AUDIT_EXPORT_CHUNK_SIZE', 1000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Application metrics
    |--------------------------------------------------------------------------
    |
    | `/metrics` is scraped by Prometheus from inside the overlay network. Set a
    | token if you ever need to expose it more widely; it is sent as a bearer
    | token and compared in constant time.
    |
    */

    'metrics' => [
        'enabled' => (bool) env('APP_METRICS_ENABLED', true),
        'token' => env('APP_METRICS_TOKEN'),
        'cache_seconds' => (int) env('APP_METRICS_CACHE_SECONDS', 60),
        'max_http_series' => (int) env('APP_METRICS_MAX_HTTP_SERIES', 500),
    ],

];
