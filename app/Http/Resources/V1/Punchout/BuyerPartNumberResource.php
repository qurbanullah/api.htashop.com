<?php

namespace App\Http\Resources\V1\Punchout;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BuyerPartNumberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $item = $this->relationLoaded('item') ? $this->item : null;

        return [
            'uuid' => $this->uuid,
            'buyer_part_number' => $item?->code,
            'code' => $item?->code,
            'short_description' => $item?->short_description,
            'long_description' => $item?->long_description,
            'unit_of_measure' => $item?->unit_of_measure,
            'manufacturer_part_number' => $item?->manufacturer_part_number,
            'gtin' => $item?->gtin,
            'unspsc' => $item?->unspsc,
            'product' => $this->whenLoaded('product', fn () => $this->product ? [
                'id' => $this->product->id,
                'uuid' => $this->product->uuid,
                'sku' => $this->product->sku,
                'name' => $this->product->name,
            ] : null),
            'variant' => $this->whenLoaded('variant', fn () => $this->variant ? [
                'id' => $this->variant->id,
                'uuid' => $this->variant->uuid,
                'sku' => $this->variant->sku,
                'name' => $this->variant->name,
            ] : null),
            'is_primary' => (bool) $this->is_primary,
            'is_active' => (bool) $this->is_active,
            'effective_from' => optional($this->effective_from)?->toDateString(),
            'effective_to' => optional($this->effective_to)?->toDateString(),
            'source' => $this->source,
            'metadata' => $this->metadata ?? [],
            'created_at' => optional($this->created_at)?->toIso8601String(),
            'updated_at' => optional($this->updated_at)?->toIso8601String(),
        ];
    }
}
