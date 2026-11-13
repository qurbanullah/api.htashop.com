<?php

namespace App\Http\Resources\V1\Brand;

use App\Http\Resources\V1\Industry\IndustryResource;
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
            'country' => $this->country,
            'origin' => $this->origin,
            'is_approved' => $this->is_approved,
            'tenant_id' => $this->tenant_id,
            'organization_id' => $this->organization_id,
            'approved_at' => $this->approved_at,
            'rejected_at' => $this->rejected_at,
            'rejection_reason' => $this->rejection_reason,
            'logo' => $this->logo,
            'website' => $this->website,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'manufacturer' => $this->whenLoaded('manufacturer'),
            'industries' => IndustryResource::collection($this->whenLoaded('industries')),
        ];
    }
}
