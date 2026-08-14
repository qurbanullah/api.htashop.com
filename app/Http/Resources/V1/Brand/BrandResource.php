<?php

namespace App\Http\Resources\V1\Brand;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BrandResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'manufacturer_id' => $this->manufacturer_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'logo' => $this->logo,
            'website' => $this->website,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'manufacturer' => $this->whenLoaded('manufacturer'),
        ];
    }
}
