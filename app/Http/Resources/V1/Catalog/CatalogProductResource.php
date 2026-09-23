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
            'image_urls' => $this->resolveImageUrls(),
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

    /**
     * Responsive URL ladder for the featured image (thumb/small/medium/large/original).
     * Missing variants resolve to null so clients only advertise real files.
     *
     * @return array<string, string|null>
     */
    private function resolveImageUrls(): array
    {
        if (! $this->relationLoaded('dams')) {
            return [];
        }

        $dam = $this->dams->first();

        if (! $dam || ! $dam->object_key) {
            return [];
        }

        return $this->damUrlLadder($dam);
    }

    /**
     * @return array<string, string|null>
     */
    protected function damUrlLadder(Dam $dam): array
    {
        $variants = data_get($dam->metadata, 'variants', []);
        $variants = is_array($variants) ? $variants : [];

        $urlFor = fn (string $key): string => 'https://cdn.htashop.com/' . ltrim($key, '/');

        $originalKey = (string) data_get($variants, 'original', $dam->object_key);

        $ladder = ['original' => $urlFor($originalKey)];
        foreach (['thumb', 'small', 'medium', 'large'] as $size) {
            $key = data_get($variants, $size);
            $ladder[$size] = is_string($key) && $key !== '' ? $urlFor($key) : null;
        }

        return $ladder;
    }
}
