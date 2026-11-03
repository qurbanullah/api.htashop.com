<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Checkout
    |--------------------------------------------------------------------------
    |
    | Master switch for placing orders. It stays off while the live payment
    | accounts are being approved: browsing, cart, quotes and order history keep
    | working, but `POST /checkout` and `POST /checkout/quote` return 503 and the
    | storefront shows a "checkout unavailable" notice.
    |
    | Set CHECKOUT_ENABLED=true once the production gateway credentials are in
    | place. This is independent of `config('payment.enabled')`, which only
    | controls hosted gateways and still falls back to Cash on Delivery.
    |
    */
    'enabled' => (bool) env('CHECKOUT_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Storefront message
    |--------------------------------------------------------------------------
    |
    | Returned by GET /checkout/status and shown verbatim by the storefront, so
    | support can explain the delay without a frontend deploy.
    |
    */
    'disabled_message' => (string) env(
        'CHECKOUT_DISABLED_MESSAGE',
        'Checkout is temporarily unavailable. Please check back soon.'
    ),
];
