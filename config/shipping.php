<?php

/*
|--------------------------------------------------------------------------
| Shipping & tax
|--------------------------------------------------------------------------
|
| Delivery charges are decided **here, on the server** — never by the client.
| The storefront asks for a quote and displays whatever it is told.
|
| A rate is chosen by the order currency: an explicit entry in `rates`, else
| `default`. `free_over` is the subtotal at or above which delivery is free;
| set it to null (or 0) for "never free".
|
| Tax is off by default. Pakistani retail prices are normally quoted
| tax-inclusive, so adding a tax line would quietly raise every basket by the
| rate. Turn it on only if you price exclusive of tax.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Master switch
    |--------------------------------------------------------------------------
    |
    | Off: every order ships free (`shipping_fee` of 0) and the storefront shows
    | no shipping line at all.
    |
    */

    'enabled' => (bool) env('SHIPPING_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Rates
    |--------------------------------------------------------------------------
    |
    | `flat_rate` is charged on every order below the free-shipping threshold.
    |
    */

    'rates' => [

        'PKR' => [
            'flat_rate' => (float) env('SHIPPING_FLAT_RATE_PKR', 250),
            'free_over' => env('SHIPPING_FREE_OVER_PKR', 10000) === null
                ? null
                : (float) env('SHIPPING_FREE_OVER_PKR', 10000),
        ],

        'USD' => [
            'flat_rate' => (float) env('SHIPPING_FLAT_RATE_USD', 9.99),
            'free_over' => env('SHIPPING_FREE_OVER_USD', 150) === null
                ? null
                : (float) env('SHIPPING_FREE_OVER_USD', 150),
        ],

    ],

    /**
     * Used for any currency without an explicit rate. No free threshold by
     * default: inventing one for an unconfigured currency would give away
     * shipping we have not costed.
     */
    'default' => [
        'flat_rate' => (float) env('SHIPPING_FLAT_RATE', 0),
        'free_over' => env('SHIPPING_FREE_OVER') === null
            ? null
            : (float) env('SHIPPING_FREE_OVER'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Tax
    |--------------------------------------------------------------------------
    |
    | When enabled, charged on the discounted subtotal (i.e. after any coupon).
    |
    */

    'tax' => [
        'enabled' => (bool) env('TAX_ENABLED', false),

        // Percent, e.g. 17 for 17%.
        'rate' => (float) env('TAX_RATE', 0),
    ],

];
