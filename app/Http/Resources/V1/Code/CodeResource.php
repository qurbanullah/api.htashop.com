<?php

namespace App\Http\Resources\V1\Code;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CodeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'organization_id' => $this->organization_id,
            'type' => $this->type,
            'value' => $this->value,
            'normalized' => $this->normalized,
            'context' => $this->context,
            'is_primary' => (bool) $this->is_primary,
            'metadata' => $this->metadata,
            'codeable' => $this->whenLoaded('codeable', fn () => $this->codeable ? [
                'type' => $this->codeable instanceof Product ? 'product' : 'variant',
                'id' => $this->codeable->id,
                'uuid' => $this->codeable->uuid,
                'name' => $this->codeable->name,
            ] : null),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
