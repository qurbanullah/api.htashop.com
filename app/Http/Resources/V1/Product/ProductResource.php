<?php

namespace App\Http\Resources\V1\Product;

use App\Rules\Gtin;
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
            'route_key' => $this->slug.'-'.substr($this->uuid, 0, 8),
            'sku' => $this->sku,
            'seller_sku' => $this->seller_sku,
            'part_number' => $this->part_number,
            'hs_code' => $this->hs_code,
            'unspsc' => $this->unspsc,
            'ntn' => $this->ntn,
            'barcode' => $this->barcode,
            'gtin' => $this->gtin,
            'gtin_type' => $this->gtin ? Gtin::type((string) $this->gtin) : null,
            'model_number' => $this->model_number,
            'sourcing' => $this->sourcing,
            'lead_time_days' => $this->lead_time_days,
            'origin_country' => $this->origin_country,
            'sourcing_url' => $this->sourcing_url,
            'supplier_reference' => $this->supplier_reference,
            'availability' => $this->availabilityLabel(),
            'status' => $this->status,
            'summary' => $this->summary,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'image_url' => $this->resolveImageUrl(),
            'videos' => $this->whenLoaded('videos', fn () => $this->resolveVideos()),
            'shipping' => $this->whenLoaded(
                'shipping',
                fn () => $this->shipping ? new ProductShippingResource($this->shipping) : null,
            ),
            'price' => data_get($this->metadata, 'price'),
            'sale_price' => data_get($this->metadata, 'sale_price'),
            'currency' => data_get($this->metadata, 'currency', 'USD'),
            'metadata' => $this->metadata,
            'channels' => $this->whenLoaded('salesChannels', fn () => $this->salesChannels->pluck('code')->values()->all()),
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

    private function resolveImageUrl(): ?string
    {
        if (! $this->relationLoaded('dams')) {
            return null;
        }

        // Videos share the DAM table, so they must never win the image slot.
        $images = $this->dams->where('collection_name', '!=', 'video');

        $featured = $images->firstWhere('collection_name', 'featured')
            ?? $images->first();

        if (! $featured || ! $featured->object_key) {
            return null;
        }

        return config('app.cdn_url', 'https://cdn.htashop.com').'/'.ltrim($featured->object_key, '/');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function resolveVideos(): array
    {
        $cdn = config('app.cdn_url', 'https://cdn.htashop.com');

        return $this->videos
            ->map(function ($dam) use ($cdn) {
                $medium = data_get($dam->metadata, 'poster_variants.medium');
                $posterKey = is_string($medium) && $medium !== ''
                    ? $medium
                    : data_get($dam->metadata, 'poster_key');

                return [
                    'id' => $dam->id,
                    'url' => $cdn.'/'.ltrim((string) $dam->object_key, '/'),
                    'poster_url' => is_string($posterKey) && $posterKey !== ''
                        ? $cdn.'/'.ltrim($posterKey, '/')
                        : null,
                    'mime_type' => $dam->mime_type,
                    'file_name' => $dam->file_name,
                    'size' => $dam->size,
                    'sort_order' => $dam->sort_order,
                ];
            })
            ->values()
            ->all();
    }
}
