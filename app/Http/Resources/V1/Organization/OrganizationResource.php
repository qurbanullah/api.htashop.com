<?php

namespace App\Http\Resources\V1\Organization;

use App\Http\Resources\V1\Dam\Concerns\SerializesDamMedia;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrganizationResource extends JsonResource
{
    use SerializesDamMedia;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'tenant_id' => $this->tenant_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'type' => $this->type,
            'code' => $this->code,
            'email' => $this->email,
            'phone' => $this->phone,
            'website' => $this->website,
            'is_active' => (bool) $this->is_active,
            'metadata' => $this->metadata,
            'primary_image' => $this->serializePrimaryDamAsset($this),
            'media' => $this->serializeDamMedia($this),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
