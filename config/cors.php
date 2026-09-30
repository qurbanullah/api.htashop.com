<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter(array_merge(
        [
            // Local development (localhost)
            'http://localhost:20050',  // API
            'http://localhost:21050',  // Admin panel
            'http://localhost:22050',  // Manage panel
            'http://localhost:23050',  // Frontend SPA

            // Local development (127.0.0.1)
            'http://127.0.0.1:20050',
            'http://127.0.0.1:21050',
            'http://127.0.0.1:22050',
            'http://127.0.0.1:23050',

            // Local development — internal Docker service names (used by the
            // prerender headless browser, which loads pages from inside the network)
            'http://frontend:23050',
            'http://manage:22050',
            'http://admin:21050',

            // Production (web)
            'https://htashop.com',
            'https://www.htashop.com',
            'https://api.htashop.com',
            'https://manage.htashop.com',
            'https://admin.htashop.com',

            // Capacitor native shells. The WebView is its own origin, so the
            // API must allow it or every request from the app fails preflight.
            // Android uses the `https` scheme (capacitor.config.json →
            // server.androidScheme); iOS defaults to `capacitor://`.
            'https://localhost',
            'http://localhost',
            'capacitor://localhost',
            'ionic://localhost',
        ],

        // Extra origins for Capacitor live-reload against a dev server on a LAN
        // address (e.g. `http://192.168.1.10:23050`). Comma-separated; leave
        // empty in production.
        array_map('trim', explode(',', (string) env('CORS_ALLOWED_ORIGINS', '')))
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['Content-Length', 'X-Request-Id'],

    'max_age' => 3600,

    'supports_credentials' => true,

];
