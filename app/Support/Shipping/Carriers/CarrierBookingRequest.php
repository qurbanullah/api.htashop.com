<?php

namespace App\Support\Shipping\Carriers;

/**
 * Everything a courier needs to book a consignment, independent of the carrier.
 */
final class CarrierBookingRequest
{
    public function __construct(
        public readonly string $orderNumber,
        public readonly string $consigneeName,
        public readonly string $consigneePhone,
        public readonly ?string $consigneeEmail,
        public readonly string $address,
        public readonly string $city,
        public readonly ?string $postalCode,
        public readonly float $weightKg,
        public readonly int $pieces,
        public readonly float $codAmount,
        public readonly ?string $instructions = null,
        public readonly ?string $productDetail = null,
    ) {}
}
