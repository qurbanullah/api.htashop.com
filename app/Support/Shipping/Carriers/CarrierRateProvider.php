<?php

namespace App\Support\Shipping\Carriers;

use App\Support\Shipping\ShippingOption;

/**
 * A live courier integration that contributes delivery options.
 *
 * Implementations must be side-effect free and fast enough to run inside a
 * checkout quote: callers apply their own timeout and treat an empty result as
 * "carrier unavailable", never as an error.
 */
interface CarrierRateProvider
{
    /** Stable carrier code stored on orders, e.g. `leopards`. */
    public function code(): string;

    /** False when credentials are missing, so the provider is skipped entirely. */
    public function isConfigured(): bool;

    /**
     * @return ShippingOption[]
     */
    public function quote(CarrierQuoteRequest $request): array;
}
