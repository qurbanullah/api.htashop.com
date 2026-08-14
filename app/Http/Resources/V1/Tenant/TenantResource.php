<?php

namespace App\Http\Resources\V1\Tenant;

use App\Http\Resources\V1\Dam\Concerns\SerializesDamMedia;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantResource extends JsonResource
{
    use SerializesDamMedia;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'slug' => $this->slug,
            'domain' => $this->domain,
            'is_active' => (bool) $this->is_active,
            'settings' => $this->settings,
            'primary_image' => $this->serializePrimaryDamAsset($this),
            'media' => $this->serializeDamMedia($this),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
