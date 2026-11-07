<?php

use App\Support\Shipping\Carriers\LeopardsBookingProvider;
use App\Support\Shipping\Carriers\LeopardsRateProvider;

/*
|--------------------------------------------------------------------------
| Shipping
|--------------------------------------------------------------------------
|
| Delivery charges are decided **here, on the server** — never by the client.
| The storefront asks for a quote and displays whatever it is told.
|
| Resolution order (see App\Services\Shipping\ShippingQuoteService):
|   1. Merchant-defined `shipping_rates` for the destination zone, using the
|      parcel's billable weight (actual or volumetric, whichever is greater).
|   2. Enabled carrier providers (Leopards is a stub until Phase 4).
|   3. The `rates` block below, keyed by order currency, used as a flat-rate
|      fallback when no zone or rate matches. `free_over` is the subtotal at or
|      above which delivery is free; null (or 0) means "never free".
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
    | Volumetric weight
    |--------------------------------------------------------------------------
    |
    | Billable weight is the greater of actual and dimetric weight. The divisor
    | mirrors `ProductShipping::volumetricWeightKg()`; 5000 cm³/kg is the common
    | domestic courier convention.
    |
    */

    'volumetric_divisor' => (float) env('SHIPPING_VOLUMETRIC_DIVISOR', 5000),

    /*
    |--------------------------------------------------------------------------
    | Carrier providers
    |--------------------------------------------------------------------------
    |
    | Live courier integrations. The rate driver contributes delivery options to
    | checkout quotes; the booking driver creates the TTN and label for a packed
    | shipment. A provider is only consulted when it is enabled *and* configured
    | (key, password), so a half-filled environment is simply skipped.
    |
    | Leopards credentials come from LEOPARDS_API_KEY / LEOPARDS_API_PASSWORD;
    | endpoint paths are configurable so a contract change is a config change.
    |
    */

    'carriers' => [

        'leopards' => [
            'driver' => LeopardsRateProvider::class,
            'booking_driver' => LeopardsBookingProvider::class,
            'enabled' => (bool) env('SHIPPING_LEOPARDS_ENABLED', false),
            'api_key' => env('LEOPARDS_API_KEY'),
            'api_password' => env('LEOPARDS_API_PASSWORD'),
            'api_base' => env('LEOPARDS_API_BASE', 'https://api.leopardscourier.com/api'),

            // Pickup city and service used for pricing and booking.
            'origin_city' => env('LEOPARDS_ORIGIN_CITY', 'Lahore'),
            'shipment_type' => env('LEOPARDS_SHIPMENT_TYPE', 'overnight'),
            'estimated_days_min' => (int) env('LEOPARDS_ETA_MIN_DAYS', 2),
            'estimated_days_max' => (int) env('LEOPARDS_ETA_MAX_DAYS', 4),

            'timeout' => (int) env('LEOPARDS_TIMEOUT', 10),
            'retries' => (int) env('LEOPARDS_RETRIES', 0),

            'rate_path' => 'getShipmentCharges/format/json',
            'book_path' => 'bookPacket/format/json',
            'cancel_path' => 'cancelBookedPacket/format/json',
        ],

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
