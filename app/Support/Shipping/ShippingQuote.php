<?php

namespace App\Support\Shipping;

/**
 * The result of pricing delivery for a basket: every option the customer may
 * choose, the one that should be preselected, and how the parcel was measured.
 *
 * Produced by `App\Services\Shipping\ShippingQuoteService` and consumed by
 * checkout pricing, so the storefront and the order always see one answer.
 */
final class ShippingQuote
{
    public const SOURCE_RATES = 'rates';

    public const SOURCE_CARRIER = 'carrier';

    public const SOURCE_CONFIG = 'config';

    public const SOURCE_DISABLED = 'disabled';

    public const SOURCE_NONE = 'none';

    /**
     * @param  ShippingOption[]  $options
     * @param  array{uuid: string, name: string, code: string}|null  $zone
     * @param  array{uuid: string, name: string, city: string|null}|null  $origin
     */
    public function __construct(
        public readonly array $options,
        public readonly ?ShippingOption $selected,
        public readonly ?array $zone,
        public readonly ParcelSummary $parcel,
        public readonly string $source,
        public readonly ?float $freeShippingThreshold = null,
        public readonly ?array $origin = null,
    ) {}

    public static function disabled(ParcelSummary $parcel): self
    {
        return new self([], null, null, $parcel, self::SOURCE_DISABLED);
    }

    public static function none(ParcelSummary $parcel): self
    {
        return new self([], null, null, $parcel, self::SOURCE_NONE);
    }

    public function fee(): float
    {
        return $this->selected?->amount ?? 0.0;
    }

    public function isFree(): bool
    {
        return $this->selected !== null && $this->selected->free;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'options' => array_map(
                fn (ShippingOption $option) => $option->toArray(),
                $this->options,
            ),
            'selected' => $this->selected?->toArray(),
            'zone' => $this->zone,
            'origin' => $this->origin,
            'weight' => $this->parcel->toArray(),
            'source' => $this->source,
            'free_shipping' => $this->isFree(),
            'free_shipping_threshold' => $this->freeShippingThreshold,
        ];
    }
}
