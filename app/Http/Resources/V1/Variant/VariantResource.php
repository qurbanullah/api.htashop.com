<?php

namespace App\Http\Resources\V1\Variant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VariantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'product_id' => $this->product_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'sku' => $this->sku,
            'seller_sku' => $this->seller_sku,
            'status' => $this->status,
            'summary' => $this->summary,
            'description' => $this->description,
            'configuration' => $this->configuration,
            'is_default' => $this->is_default,
            'is_active' => $this->is_active,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
