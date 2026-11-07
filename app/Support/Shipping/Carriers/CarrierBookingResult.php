<?php

namespace App\Support\Shipping\Carriers;

/**
 * The carrier's answer to a booking: the identifier needed to cancel or
 * reconcile it, the customer-facing tracking number, and a printable label.
 */
final class CarrierBookingResult
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly ?string $bookingId,
        public readonly string $trackingNumber,
        public readonly ?string $labelUrl,
        public readonly ?string $trackingUrl = null,
        public readonly array $raw = [],
    ) {}
}
