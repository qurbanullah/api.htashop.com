<?php

namespace App\Support\Shipping;

/**
 * A suggested packing of one shipment: the boxes, their contents and any
 * warnings the planner could not resolve automatically.
 */
final class PackingPlan
{
    /**
     * @param  PackedBox[]  $boxes
     * @param  string[]  $warnings
     */
    public function __construct(
        public readonly array $boxes,
        public readonly array $warnings = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'boxes' => array_map(fn (PackedBox $box) => $box->toArray(), $this->boxes),
            'warnings' => array_values($this->warnings),
        ];
    }
}
