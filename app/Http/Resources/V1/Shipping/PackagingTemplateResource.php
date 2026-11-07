<?php

namespace App\Http\Resources\V1\Shipping;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PackagingTemplateResource extends JsonResource
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

            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,

            'packaging_type_unit_id' => $this->packaging_type_unit_id,
            'packaging_type' => $this->whenLoaded('packagingType', fn () => $this->unitPayload($this->packagingType)),

            'length' => $this->number($this->length),
            'width' => $this->number($this->width),
            'height' => $this->number($this->height),
            'dimension_unit_id' => $this->dimension_unit_id,
            'dimension_unit' => $this->whenLoaded('dimensionUnit', fn () => $this->unitPayload($this->dimensionUnit)),
            'length_cm' => $this->number($this->length_cm),
            'width_cm' => $this->number($this->width_cm),
            'height_cm' => $this->number($this->height_cm),
            'volume_cm3' => $this->volumeCm3(),

            'max_weight' => $this->number($this->max_weight),
            'weight_unit_id' => $this->weight_unit_id,
            'weight_unit' => $this->whenLoaded('weightUnit', fn () => $this->unitPayload($this->weightUnit)),
            'max_weight_kg' => $this->number($this->max_weight_kg),

            'tare_weight' => $this->number($this->tare_weight),
            'tare_weight_unit_id' => $this->tare_weight_unit_id,
            'tare_weight_unit' => $this->whenLoaded('tareWeightUnit', fn () => $this->unitPayload($this->tareWeightUnit)),
            'tare_weight_kg' => $this->number($this->tare_weight_kg),

            'is_active' => (bool) $this->is_active,
            'sort_order' => (int) $this->sort_order,
            'metadata' => $this->metadata ?? [],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    private function number(mixed $value): ?float
    {
        return $value === null ? null : (float) $value;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function unitPayload(mixed $unit): ?array
    {
        if (! $unit) {
            return null;
        }

        return [
            'id' => $unit->id,
            'name' => $unit->name,
            'code' => $unit->code,
            'symbol' => $unit->symbol,
        ];
    }
}
