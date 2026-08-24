<?php

namespace App\Http\Resources\V1\Price;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PriceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'organization_id' => $this->organization_id,
            'contract_id' => $this->contract_id,
            'catalog_id' => $this->catalog_id,
            'currency_id' => $this->currency_id,
            'unit_id' => $this->unit_id,
            'type' => $this->type,
            'base_price' => $this->base_price,
            'min_quantity' => $this->min_quantity,
            'max_quantity' => $this->max_quantity,
            'starts_at' => $this->starts_at?->toISOString(),
            'ends_at' => $this->ends_at?->toISOString(),
            'priority' => $this->priority,
            'is_active' => (bool) $this->is_active,
            'metadata' => $this->metadata,
            'currency' => $this->whenLoaded('currency', fn () => $this->currency ? ['id' => $this->currency->id, 'code' => $this->currency->code, 'symbol' => $this->currency->symbol] : null),
            'priceable' => $this->whenLoaded('priceable', fn () => $this->priceable ? [
                'type' => $this->priceable instanceof Product ? 'product' : 'variant',
                'id' => $this->priceable->id,
                'uuid' => $this->priceable->uuid,
                'name' => $this->priceable->name,
            ] : null),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
