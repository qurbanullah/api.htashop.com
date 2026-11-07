<?php

namespace App\Http\Controllers\V1\Shipping;

use App\Http\Controllers\Controller;
use App\Http\Responses\V1\ApiResponse;
use App\Support\Shipping\Carriers\CarrierBookingManager;
use App\Support\Shipping\Carriers\CarrierRateManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

/**
 * Which carriers this installation can actually use.
 *
 * The management UI uses this to offer a carrier picker and to decide whether a
 * shipment can be booked, so it never shows an action that would fail.
 */
class ShippingCarrierController extends Controller
{
    public function __construct(
        protected CarrierRateManager $rates,
        protected CarrierBookingManager $bookings,
    ) {}

    public function index(): JsonResponse
    {
        $rateCodes = array_map(fn ($provider) => $provider->code(), $this->rates->active());
        $bookingCodes = array_map(fn ($provider) => $provider->code(), $this->bookings->active());

        $carriers = array_map(fn (string $code) => [
            'code' => $code,
            'name' => Str::headline($code),
            'rates' => in_array($code, $rateCodes, true),
            'booking' => in_array($code, $bookingCodes, true),
        ], array_values(array_unique(array_merge($rateCodes, $bookingCodes))));

        return ApiResponse::success($carriers, 'Carriers retrieved successfully');
    }
}
