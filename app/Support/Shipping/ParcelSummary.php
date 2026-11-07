<?php

namespace App\Support\Shipping;

/**
 * What the parcel planner knows about a basket before a box is chosen.
 *
 * Dimensions are a conservative envelope until Phase 5 bin packing lands: the
 * widest footprint in the basket, with stackable heights summed. Volumetric
 * weight uses the same divisor as `ProductShipping::volumetricWeightKg()`.
 */
final class ParcelSummary
{
    public function __construct(
        public readonly float $actualWeightKg,
        public readonly float $volumetricWeightKg,
        public readonly ?float $billableWeightKg,
        public readonly bool $weightComplete,
        public readonly int $itemCount,
        public readonly ?float $lengthCm = null,
        public readonly ?float $widthCm = null,
        public readonly ?float $heightCm = null,
    ) {}

    public static function empty(): self
    {
        return new self(0.0, 0.0, null, true, 0);
    }

    public function hasDimensions(): bool
    {
        return $this->lengthCm !== null && $this->widthCm !== null && $this->heightCm !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'actual_kg' => round($this->actualWeightKg, 3),
            'volumetric_kg' => round($this->volumetricWeightKg, 3),
            'billable_kg' => $this->billableWeightKg !== null
                ? round($this->billableWeightKg, 3)
                : null,
            'complete' => $this->weightComplete,
            'item_count' => $this->itemCount,
            'length_cm' => $this->lengthCm,
            'width_cm' => $this->widthCm,
            'height_cm' => $this->heightCm,
        ];
    }
}
