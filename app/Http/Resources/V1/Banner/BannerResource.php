<?php

namespace App\Http\Resources\V1\Banner;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BannerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $cdn = config('app.cdn_url', 'https://cdn.htashop.com');

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'body' => $this->body,
            'image_key' => $this->image_key,
            'mobile_image_key' => $this->mobile_image_key,
            'image_url' => $this->image_key ? $cdn . '/' . ltrim($this->image_key, '/') : null,
            'mobile_image_url' => $this->mobile_image_key ? $cdn . '/' . ltrim($this->mobile_image_key, '/') : null,
            'link_type' => $this->link_type,
            'link_value' => $this->link_value,
            'type' => $this->type,
            'placement' => $this->placement,
            'category_ids' => $this->category_ids ?? [],
            'brand_ids' => $this->brand_ids ?? [],
            'product_ids' => $this->product_ids ?? [],
            'search_keywords' => $this->search_keywords ?? [],
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'deleted_at' => optional($this->deleted_at)?->format('Y-m-d\TH:i'),
            'starts_at' => optional($this->starts_at)?->format('Y-m-d\TH:i'),
            'ends_at' => optional($this->ends_at)?->format('Y-m-d\TH:i'),
        ];
    }
}
