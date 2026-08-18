<?php

namespace App\Http\Resources\V1\Catalog;

use App\Models\Dam;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CatalogProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'slug' => $this->slug,
            'route_key' => $this->slug . '-' . substr($this->uuid, 0, 8),
            'summary' => $this->summary,
            'status' => $this->status,
            'is_active' => $this->is_active,
            'price' => data_get($this->metadata, 'price'),
            'sale_price' => data_get($this->metadata, 'sale_price'),
            'currency' => data_get($this->metadata, 'currency', 'USD'),
            'image_url' => $this->resolveImageUrl(),
            'categories' => $this->whenLoaded('categories'),
            'brands' => $this->whenLoaded('brands'),
            'features' => $this->whenLoaded('features'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    private function resolveImageUrl(): ?string
    {
        if (! $this->relationLoaded('dams')) {
            return null;
        }

        $dam = $this->dams->first();

        return $dam ? $this->variantUrl($dam, 'medium') : null;
    }

    private function variantUrl(Dam $dam, string $size): ?string
    {
        if (! $dam->object_key) {
            return null;
        }

        $key = data_get($dam->metadata, "variants.{$size}", $dam->object_key);

        return 'https://cdn.htashop.com/' . ltrim((string) $key, '/');
    }
}
