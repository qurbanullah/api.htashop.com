<?php

return [

    /*
    |--------------------------------------------------------------------------
    | IndexNow (Bing, DuckDuckGo, Yandex, Seznam, Naver, Yep)
    |--------------------------------------------------------------------------
    |
    | Instant indexing notifications for engines that support IndexNow.
    | Google does NOT participate in IndexNow — it relies on the sitemap.
    |
    | 1. Generate a random key:  openssl rand -hex 16
    | 2. The key file is served automatically at https://<host>/<key>.txt
    | 3. Verify the key in Bing Webmaster Tools.
    |
    */

    'enabled' => (bool) env('INDEXNOW_ENABLED', false),

    'key' => (string) env('INDEXNOW_KEY', ''),

    /**
     * Publisher host (scheme-less). Derived from the storefront URL when empty.
     */
    'host' => (string) env('INDEXNOW_HOST', ''),

    'endpoint' => (string) env('INDEXNOW_ENDPOINT', 'https://api.indexnow.org/indexnow'),
];
