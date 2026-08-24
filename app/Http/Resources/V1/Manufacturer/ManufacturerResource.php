<?php

namespace App\Http\Resources\V1\Manufacturer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ManufacturerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'slug' => $this->slug,
            'code' => $this->code,
            'type' => $this->type,
            'origin' => $this->origin,
            'is_approved' => $this->is_approved,
            'tenant_id' => $this->tenant_id,
            'organization_id' => $this->organization_id,
            'approved_at' => $this->approved_at,
            'rejected_at' => $this->rejected_at,
            'rejection_reason' => $this->rejection_reason,
            'logo' => $this->logo,
            'website' => $this->website,
            'country' => $this->country,
            'description' => $this->description,
            'is_active' => $this->is_active,
        ];
    }
}
