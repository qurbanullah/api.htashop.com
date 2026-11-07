<?php

namespace App\Http\Resources\V1\Catalog;

use App\Models\Dam;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CatalogProductDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'slug' => $this->slug,
            'route_key' => $this->slug.'-'.substr($this->uuid, 0, 8),
            'summary' => $this->summary,
            'description' => $this->description,
            'status' => $this->status,
            'is_active' => $this->is_active,
            'price' => data_get($this->metadata, 'price'),
            'sale_price' => data_get($this->metadata, 'sale_price'),
            'currency' => data_get($this->metadata, 'currency', 'USD'),
            'sourcing' => $this->sourcing,
            'lead_time_days' => $this->lead_time_days,
            'availability' => $this->availabilityLabel(),
            'channels' => $this->whenLoaded('salesChannels', fn () => $this->salesChannels->pluck('code')->values()->all()),
            'specs' => data_get($this->metadata, 'specs', []),
            'image_url' => $this->resolveImageUrl(),
            'image_original_url' => $this->resolveOriginalImageUrl(),
            'image_urls' => $this->resolveFeaturedUrls(),
            'gallery' => $this->resolveGallery(),
            'gallery_original' => $this->resolveOriginalGallery(),
            'gallery_sizes' => $this->resolveGallerySizes(),
            'videos' => $this->whenLoaded('videos', fn () => $this->resolveVideos()),
            'packaging' => $this->whenLoaded('shipping', fn () => $this->resolvePackaging()),
            'categories' => $this->whenLoaded('categories'),
            'brands' => $this->whenLoaded('brands'),
            'features' => $this->whenLoaded('features'),
            'manufacturers' => $this->whenLoaded('manufacturers'),
            'highlights' => $this->whenLoaded('highlights', fn () => $this->highlights->map(fn ($highlight) => [
                'id' => $highlight->id,
                'label' => $highlight->label,
                'heading' => $this->overrideValue($highlight->pivot->heading_override, $highlight->heading),
                'body' => $this->overrideValue($highlight->pivot->body_override, $highlight->body),
            ])->values()->all()),
            'variants' => $this->whenLoaded('variants', fn () => $this->resolveVariants()),
            'stock' => $this->resolveStock(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    private function overrideValue(?string $override, ?string $fallback): ?string
    {
        return $override !== null && $override !== '' ? $override : $fallback;
    }

    private function resolveImageUrl(): ?string
    {
        if (! $this->relationLoaded('dams')) {
            return null;
        }

        $featured = $this->dams->firstWhere('collection_name', 'featured')
            ?? $this->dams->first();

        return $featured ? $this->variantUrl($featured, 'medium') : null;
    }

    private function resolveOriginalImageUrl(): ?string
    {
        if (! $this->relationLoaded('dams')) {
            return null;
        }

        $featured = $this->dams->firstWhere('collection_name', 'featured')
            ?? $this->dams->first();

        return $featured ? $this->variantUrl($featured, 'original') : null;
    }

    private function resolveGallery(): array
    {
        if (! $this->relationLoaded('dams')) {
            return [];
        }

        return $this->dams
            ->map(fn (Dam $dam) => $this->variantUrl($dam, 'medium'))
            ->filter()
            ->values()
            ->all();
    }

    private function resolveOriginalGallery(): array
    {
        if (! $this->relationLoaded('dams')) {
            return [];
        }

        return $this->dams
            ->map(fn (Dam $dam) => $this->variantUrl($dam, 'original'))
            ->filter()
            ->values()
            ->all();
    }

    private function resolveFeaturedUrls(): array
    {
        if (! $this->relationLoaded('dams')) {
            return [];
        }

        $featured = $this->dams->firstWhere('collection_name', 'featured')
            ?? $this->dams->first();

        return $featured ? $this->damLadder($featured) : [];
    }

    /**
     * Per-image URL ladders aligned with `gallery` (index-for-index).
     *
     * @return array<int, array<string, string|null>>
     */
    private function resolveGallerySizes(): array
    {
        if (! $this->relationLoaded('dams')) {
            return [];
        }

        return $this->dams
            ->map(fn (Dam $dam) => $this->damLadder($dam))
            ->filter(fn (array $ladder) => ! empty($ladder['medium']))
            ->values()
            ->all();
    }

    /**
     * @return array<string, string|null>
     */
    private function damLadder(Dam $dam): array
    {
        $variants = data_get($dam->metadata, 'variants', []);
        $variants = is_array($variants) ? $variants : [];

        $urlFor = fn (string $key): string => 'https://cdn.htashop.com/'.ltrim($key, '/');
        $originalKey = (string) data_get($variants, 'original', $dam->object_key ?? '');

        $ladder = ['original' => $originalKey !== '' ? $urlFor($originalKey) : null];
        foreach (['thumb', 'small', 'medium', 'large'] as $size) {
            $key = data_get($variants, $size);
            $ladder[$size] = is_string($key) && $key !== '' ? $urlFor($key) : null;
        }

        return $ladder;
    }

    /**
     * Product videos, ordered by the DAM sort order.
     *
     * @return array<int, array<string, mixed>>
     */
    private function resolveVideos(): array
    {
        return $this->videos
            ->map(fn (Dam $dam) => [
                'id' => $dam->id,
                'url' => $this->cdnUrl((string) $dam->object_key),
                'poster_url' => $this->videoPosterUrl($dam),
                'mime_type' => $dam->mime_type,
                'file_name' => $dam->file_name,
                'size' => $dam->size,
                'sort_order' => $dam->sort_order,
            ])
            ->values()
            ->all();
    }

    private function videoPosterUrl(Dam $dam): ?string
    {
        $medium = data_get($dam->metadata, 'poster_variants.medium');
        $key = is_string($medium) && $medium !== ''
            ? $medium
            : data_get($dam->metadata, 'poster_key');

        return is_string($key) && $key !== '' ? $this->cdnUrl($key) : null;
    }

    private function cdnUrl(string $key): string
    {
        return 'https://cdn.htashop.com/'.ltrim($key, '/');
    }

    /**
     * Public packaging / shipping facts for the product detail page.
     *
     * @return array<string, mixed>|null
     */
    private function resolvePackaging(): ?array
    {
        $shipping = $this->shipping;

        if (! $shipping) {
            return null;
        }

        $number = fn (mixed $value): ?float => $value === null ? null : (float) $value;

        return [
            'net_weight' => $number($shipping->net_weight),
            'gross_weight' => $number($shipping->gross_weight),
            'weight_unit' => $shipping->weightUnit?->symbol,
            'gross_weight_kg' => $number($shipping->gross_weight_kg),
            'length' => $number($shipping->length),
            'width' => $number($shipping->width),
            'height' => $number($shipping->height),
            'dimension_unit' => $shipping->dimensionUnit?->symbol,
            'length_cm' => $number($shipping->length_cm),
            'width_cm' => $number($shipping->width_cm),
            'height_cm' => $number($shipping->height_cm),
            'packaging_type' => $shipping->packagingType
                ? ['code' => $shipping->packagingType->code, 'name' => $shipping->packagingType->name]
                : null,
            'units_per_case' => (int) $shipping->units_per_case,
            'ships_alone' => (bool) $shipping->ships_alone,
            'is_fragile' => (bool) $shipping->is_fragile,
            'is_stackable' => (bool) $shipping->is_stackable,
            'is_hazmat' => (bool) $shipping->is_hazmat,
            'handling_notes' => $shipping->handling_notes,
            'contents' => $shipping->contents ?? [],
            'is_rateable' => $shipping->isRateable(),
            'volumetric_weight_kg' => $shipping->volumetricWeightKg(),
        ];
    }

    private function resolveVariants(): array
    {
        return $this->variants->map(function ($variant) {
            return [
                'id' => $variant->id,
                'uuid' => $variant->uuid,
                'name' => $variant->name,
                'sku' => $variant->sku,
                'configuration' => $variant->configuration,
                'price' => data_get($variant->metadata, 'price'),
                'sale_price' => data_get($variant->metadata, 'sale_price'),
                'currency' => data_get($variant->metadata, 'currency', data_get($this->metadata, 'currency', 'USD')),
                'is_default' => $variant->is_default,
                'image_url' => $this->variantImageUrl($variant),
            ];
        })->all();
    }

    private function variantImageUrl($variant): ?string
    {
        if (! $variant->relationLoaded('dams')) {
            return null;
        }

        $dam = $variant->dams->first();

        return $dam ? $this->variantUrl($dam, 'medium') : null;
    }

    private function resolveStock(): array
    {
        if (! $this->relationLoaded('inventories')) {
            return [
                'track_inventory' => false,
                'available' => 0,
                'low_stock' => false,
            ];
        }

        $available = $this->inventories->sum(fn ($inventory) => max(0, $inventory->available));

        return [
            'track_inventory' => $this->inventories->isNotEmpty(),
            'available' => $available,
            'low_stock' => $this->inventories->contains(fn ($inventory) => $inventory->isLowStock()),
        ];
    }

    private function variantUrl(Dam $dam, string $size): ?string
    {
        if (! $dam->object_key) {
            return null;
        }

        $key = data_get($dam->metadata, "variants.{$size}", $dam->object_key);

        return 'https://cdn.htashop.com/'.ltrim((string) $key, '/');
    }
}
