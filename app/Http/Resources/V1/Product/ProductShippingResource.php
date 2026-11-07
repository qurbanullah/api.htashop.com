<?php

namespace App\Http\Resources\V1\Product;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductShippingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'net_weight' => $this->number($this->net_weight),
            'gross_weight' => $this->number($this->gross_weight),
            'weight_unit_id' => $this->weight_unit_id,
            'weight_unit' => $this->whenLoaded(
                'weightUnit',
                fn () => $this->unitPayload($this->weightUnit),
            ),
            'gross_weight_kg' => $this->number($this->gross_weight_kg),

            'length' => $this->number($this->length),
            'width' => $this->number($this->width),
            'height' => $this->number($this->height),
            'dimension_unit_id' => $this->dimension_unit_id,
            'dimension_unit' => $this->whenLoaded(
                'dimensionUnit',
                fn () => $this->unitPayload($this->dimensionUnit),
            ),
            'length_cm' => $this->number($this->length_cm),
            'width_cm' => $this->number($this->width_cm),
            'height_cm' => $this->number($this->height_cm),

            'packaging_type_unit_id' => $this->packaging_type_unit_id,
            'packaging_type' => $this->whenLoaded(
                'packagingType',
                fn () => $this->unitPayload($this->packagingType),
            ),
            'units_per_case' => $this->units_per_case,

            'ships_alone' => (bool) $this->ships_alone,
            'is_fragile' => (bool) $this->is_fragile,
            'is_stackable' => (bool) $this->is_stackable,
            'is_hazmat' => (bool) $this->is_hazmat,

            'un_number' => $this->un_number,
            'hazard_class_option_id' => $this->hazard_class_option_id,
            'hazard_class' => $this->whenLoaded(
                'hazardClass',
                fn () => $this->optionPayload($this->hazardClass),
            ),
            'packing_group_option_id' => $this->packing_group_option_id,
            'packing_group' => $this->whenLoaded(
                'packingGroup',
                fn () => $this->optionPayload($this->packingGroup),
            ),

            'handling_notes' => $this->handling_notes,
            'contents' => $this->contents ?? [],
            'is_rateable' => $this->isRateable(),
            'volumetric_weight_kg' => $this->volumetricWeightKg(),
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

    /**
     * @return array<string, mixed>|null
     */
    private function optionPayload(mixed $option): ?array
    {
        if (! $option) {
            return null;
        }

        return [
            'id' => $option->id,
            'name' => $option->name,
            'code' => $option->code,
        ];
    }
}
