<?php

namespace App\Support\Shipping;

/**
 * One box produced by the packing planner (or entered by an operator).
 *
 * `items` is a list of `{order_item_id, quantity}`; the sum across a shipment's
 * boxes may not exceed what the shipment itself carries.
 */
final class PackedBox
{
    /**
     * @param  array<int, array{order_item_id: int, quantity: float}>  $items
     */
    public function __construct(
        public readonly ?int $templateId,
        public readonly ?string $templateName,
        public readonly ?float $lengthCm,
        public readonly ?float $widthCm,
        public readonly ?float $heightCm,
        public readonly float $weightKg,
        public readonly bool $containsHazmat,
        public readonly ?int $hazardClassOptionId,
        public readonly array $items,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'packaging_template_id' => $this->templateId,
            'packaging_template' => $this->templateName !== null
                ? ['name' => $this->templateName]
                : null,
            'length_cm' => $this->lengthCm,
            'width_cm' => $this->widthCm,
            'height_cm' => $this->heightCm,
            'weight_kg' => round($this->weightKg, 3),
            'contains_hazmat' => $this->containsHazmat,
            'hazard_class_option_id' => $this->hazardClassOptionId,
            'items' => $this->items,
        ];
    }
}
