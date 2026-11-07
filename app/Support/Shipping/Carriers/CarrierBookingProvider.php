<?php

namespace App\Support\Shipping\Carriers;

/**
 * A courier that can turn a packed shipment into a consignment (TTN + label).
 */
interface CarrierBookingProvider
{
    /** Stable carrier code stored on shipments, e.g. `leopards`. */
    public function code(): string;

    /** False when credentials are missing, so the provider is skipped entirely. */
    public function isConfigured(): bool;

    public function book(CarrierBookingRequest $request): CarrierBookingResult;

    /**
     * Void a booking with the reference returned by the carrier (the tracking
     * number for Leopards).
     */
    public function cancel(string $reference): void;
}
