<?php

namespace App\Support\Shipping;

/**
 * One delivery choice the customer may pick at checkout.
 *
 * `id` is the `shipping_rates.uuid` for configured rates, or a synthetic
 * `config:*` id when the legacy flat-rate fallback priced the basket.
 */
final class ShippingOption
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $code,
        public readonly string $carrier,
        public readonly float $amount,
        public readonly string $currency,
        public readonly bool $free,
        public readonly ?int $estimatedDaysMin = null,
        public readonly ?int $estimatedDaysMax = null,
        public readonly ?float $freeOverAmount = null,
    ) {}

    public function matches(string $id): bool
    {
        return $this->id === $id;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'carrier' => $this->carrier,
            'amount' => round($this->amount, 2),
            'currency' => $this->currency,
            'free' => $this->free,
            'estimated_days_min' => $this->estimatedDaysMin,
            'estimated_days_max' => $this->estimatedDaysMax,
            'free_over_amount' => $this->freeOverAmount,
        ];
    }
}
