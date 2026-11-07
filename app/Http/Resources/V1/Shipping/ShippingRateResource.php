<?php

namespace App\Http\Resources\V1\Shipping;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShippingRateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'tenant_id' => $this->tenant_id,
            'tenant' => $this->whenLoaded('tenant', fn () => $this->tenant ? [
                'id' => $this->tenant->id,
                'name' => $this->tenant->name,
            ] : null),

            'shipping_zone_id' => $this->shipping_zone_id,
            'zone' => $this->whenLoaded('zone', fn () => $this->zone ? [
                'id' => $this->zone->id,
                'uuid' => $this->zone->uuid,
                'name' => $this->zone->name,
                'code' => $this->zone->code,
            ] : null),

            'warehouse_id' => $this->warehouse_id,
            'warehouse' => $this->whenLoaded('warehouse', fn () => $this->warehouse ? [
                'id' => $this->warehouse->id,
                'uuid' => $this->warehouse->uuid,
                'name' => $this->warehouse->name,
                'city' => $this->warehouse->city,
            ] : null),

            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,

            'carrier' => $this->carrier,
            'calculation' => $this->calculation,
            'currency' => $this->currency,

            'base_amount' => $this->number($this->base_amount),
            'included_weight_kg' => $this->number($this->included_weight_kg),
            'per_kg_amount' => $this->number($this->per_kg_amount),
            'min_weight_kg' => $this->number($this->min_weight_kg),
            'max_weight_kg' => $this->number($this->max_weight_kg),
            'free_over_amount' => $this->number($this->free_over_amount),

            'estimated_days_min' => $this->estimated_days_min,
            'estimated_days_max' => $this->estimated_days_max,
            'priority' => (int) $this->priority,
            'is_active' => (bool) $this->is_active,
            'carries_hazmat' => (bool) $this->carries_hazmat,
            'metadata' => $this->metadata ?? [],

            'tiers' => $this->whenLoaded('tiers', fn () => $this->tiers->map(fn ($tier) => [
                'id' => $tier->id,
                'min_weight_kg' => (float) $tier->min_weight_kg,
                'max_weight_kg' => $tier->max_weight_kg !== null ? (float) $tier->max_weight_kg : null,
                'amount' => (float) $tier->amount,
            ])->values()->all()),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    private function number(mixed $value): ?float
    {
        return $value === null ? null : (float) $value;
    }
}
