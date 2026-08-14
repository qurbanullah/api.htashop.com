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

    'allowed_origins' => [
        // Local development (localhost)
        'http://localhost:20050',  // API
        'http://localhost:21050',  // Frontend SPA
        'http://localhost:22050',  // Manage panel
        'http://localhost:23050',  // Admin panel

        // Local development (127.0.0.1)
        'http://127.0.0.1:20050',
        'http://127.0.0.1:21050',
        'http://127.0.0.1:22050',
        'http://127.0.0.1:23050',

        // Production
        'https://htashop.com',
        'https://www.htashop.com',
        'https://api.htashop.com',
        'https://manage.htashop.com',
        'https://admin.htashop.com',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['Content-Length', 'X-Request-Id'],

    'max_age' => 3600,

    'supports_credentials' => true,

];
