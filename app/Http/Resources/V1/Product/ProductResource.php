<?php

namespace App\Http\Resources\V1\Product;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'slug' => $this->slug,
            'route_key' => $this->slug . '-' . substr($this->uuid, 0, 8),
            'sku' => $this->sku,
            'seller_sku' => $this->seller_sku,
            'part_number' => $this->part_number,
            'hs_code' => $this->hs_code,
            'unspsc' => $this->unspsc,
            'ntn' => $this->ntn,
            'barcode' => $this->barcode,
            'model_number' => $this->model_number,
            'status' => $this->status,
            'summary' => $this->summary,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'metadata' => $this->metadata,
            'tenant_id' => $this->tenant_id,
            'organization_id' => $this->organization_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'categories' => $this->whenLoaded('categories'),
            'tags' => $this->whenLoaded('tags'),
            'features' => $this->whenLoaded('features'),
            'manufacturers' => $this->whenLoaded('manufacturers'),
            'brands' => $this->whenLoaded('brands'),
            'variants' => $this->whenLoaded('variants'),
        ];
    }
}
