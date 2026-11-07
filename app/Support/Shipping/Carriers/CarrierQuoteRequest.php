<?php

namespace App\Support\Shipping\Carriers;

/**
 * Everything a courier needs to price a domestic parcel.
 *
 * Deliberately transport-agnostic: providers map this onto HTTP calls, and the
 * quote engine never knows a carrier exists beyond the options it returns.
 */
final class CarrierQuoteRequest
{
    public function __construct(
        public readonly float $weightKg,
        public readonly ?float $lengthCm,
        public readonly ?float $widthCm,
        public readonly ?float $heightCm,
        public readonly string $currency,
        public readonly float $subtotal,
        public readonly int $itemCount,
        public readonly ?string $countryCode = null,
        public readonly ?string $city = null,
        public readonly ?string $postalCode = null,
        public readonly bool $containsHazmat = false,
    ) {}

    public function hasDimensions(): bool
    {
        return $this->lengthCm !== null && $this->widthCm !== null && $this->heightCm !== null;
    }
}
