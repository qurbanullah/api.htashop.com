<?php

use App\Gateways\SafepayGateway;

/*
|--------------------------------------------------------------------------
| Payments
|--------------------------------------------------------------------------
|
| Every gateway is described here and resolved through
| App\Gateways\PaymentGatewayManager, so the checkout flow only ever talks to
| the PaymentGateway interface. Turning a gateway on is a config change, not a
| code change.
|
| Two independent conditions decide whether a gateway is offered at the
| checkout:
|
|   1. `enabled` is true, and
|   2. it has credentials.
|
| A gateway that is switched on but unconfigured is reported as *unavailable*
| rather than erroring, so a half-finished rollout cannot break checkout.
|
| Cash on Delivery is not listed in `gateways`: it has no credentials and
| cannot be switched off. When every hosted gateway is disabled or
| unconfigured, COD is the only method the storefront is offered.
|
*/

/*
| Where a hosted gateway returns the customer. The base mirrors
| config/app.php's fallback on purpose: an *empty* base here would yield a
| relative `/checkout/success`, which the gateway resolves against **its own**
| domain — the customer pays and lands on the provider's 404. An absolute
| fallback cannot fail that way.
*/

$frontendUrl = rtrim(
    (string) env('FRONTEND_URL', env('APP_ENV') === 'production' ? 'https://htashop.com' : 'http://localhost:23050'),
    '/',
);

return [

    /*
    |--------------------------------------------------------------------------
    | Master switch
    |--------------------------------------------------------------------------
    |
    | Off: every hosted gateway reports itself unavailable and checkout falls
    | back to COD only. Nothing else in the app needs to change.
    |
    */

    'enabled' => (bool) env('PAYMENTS_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    |
    | Hosted gateways are only offered when the order currency matches, so a
    | PKR-only gateway never appears on a USD order.
    |
    */

    'currency' => strtoupper((string) env('PAYMENT_CURRENCY', 'PKR')),

    /*
    |--------------------------------------------------------------------------
    | Return URLs
    |--------------------------------------------------------------------------
    |
    | Where the customer lands after finishing (or abandoning) a hosted
    | checkout. The gateway appends its own parameters (`?order_id=…&tracker=…`);
    | the storefront is expected to read the order's status rather than trust
    | them. These must be absolute and must have no query string of their own.
    |
    */

    'redirects' => [
        'success' => env('PAYMENT_SUCCESS_URL', $frontendUrl.'/checkout/success'),
        'cancel' => env('PAYMENT_CANCEL_URL', $frontendUrl.'/checkout/cancel'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Gateways
    |--------------------------------------------------------------------------
    |
    | The key is the PaymentMethod value. `driver` is the class the manager
    | resolves out of the container — add a class, add an entry, add the key to
    | App\Enums\PaymentMethod, and the gateway starts working.
    |
    */

    'gateways' => [

        'safepay' => [
            'driver' => SafepayGateway::class,

            'enabled' => (bool) env('SAFEPAY_ENABLED', true),

            // `sandbox` (or `development`) while integrating; `production` to
            // take live payments. This selects both the API base URL and the
            // hosted checkout host.
            'environment' => env('SAFEPAY_ENVIRONMENT', 'sandbox'),

            // Currencies this merchant account can settle. The storefront is
            // only offered the gateway when the order currency is in this list.
            'currencies' => array_values(array_filter(array_map(
                'trim',
                explode(',', (string) env('SAFEPAY_CURRENCIES', 'PKR,USD')),
            ))),

            // The public key identifies the merchant; the secret key
            // authenticates our server. Both come from the Safepay dashboard
            // under Developer → Keys.
            'public_key' => env('SAFEPAY_PUBLIC_API_KEY'),
            'secret_key' => env('SAFEPAY_SECRET_API_KEY'),

            // A separate value from Developer → Endpoints. Safepay signs every
            // webhook with it (HMAC-SHA512 over the raw body). Without it we
            // refuse callbacks rather than trusting an unsigned request.
            'webhook_secret' => env('SAFEPAY_WEBHOOK_SECRET'),

            // The processing intent agreed with Safepay for this account.
            'intent' => env('SAFEPAY_INTENT', 'CYBERSOURCE'),

            // Sent to the checkout as `source`. This is **not** free-form: the
            // hosted page switches on it, and an unrecognised value renders no
            // handler at all — the customer pays and is then stranded on
            // SafePay's completion page. Known values, read out of SafePay's own
            // checkout bundle: `hosted` (top-level redirect, what we use),
            // `mobile`, `xcomponent`, `popup`, `woocommerce`, `shopify`.
            'source' => env('SAFEPAY_CHECKOUT_SOURCE', 'hosted'),

            'timeout' => (int) env('SAFEPAY_TIMEOUT_SECONDS', 20),
            'retries' => (int) env('SAFEPAY_RETRIES', 1),

            // Overrides, for pointing at a mock during tests.
            'api_base' => env('SAFEPAY_API_BASE'),
            'checkout_base' => env('SAFEPAY_CHECKOUT_BASE'),
        ],

        // JazzCash / EasyPaisa / UPaisa slot in here. Each needs its own driver
        // class because the protocols are unrelated; nothing in the checkout,
        // order or ledger code changes when they land:
        //
        // 'jazzcash' => [
        //     'driver' => App\Gateways\JazzcashGateway::class,
        //     'enabled' => (bool) env('JAZZCASH_ENABLED', false),
        //     'merchant_id' => env('JAZZCASH_MERCHANT_ID'),
        //     'password' => env('JAZZCASH_PASSWORD'),
        //     'integrity_salt' => env('JAZZCASH_INTEGRITY_SALT'),
        // ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Webhooks
    |--------------------------------------------------------------------------
    |
    | Gateway callbacks are unauthenticated by necessity, so they are bounded
    | per IP. A retrying gateway is normal; a flood is not.
    |
    */

    'webhooks' => [
        'per_minute' => (int) env('PAYMENT_WEBHOOK_RATE_LIMIT', 120),
    ],

];
