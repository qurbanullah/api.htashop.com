<?php

namespace App\Http\Resources\V1\Assignment;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'organization_id' => $this->organization_id,
            'role' => $this->role,
            'is_primary' => (bool) $this->is_primary,
            'metadata' => $this->metadata,
            'assignable' => $this->whenLoaded('assignable', fn () => $this->assignable ? [
                'type' => $this->assignable instanceof Product ? 'product' : 'variant',
                'id' => $this->assignable->id,
                'uuid' => $this->assignable->uuid,
                'name' => $this->assignable->name,
            ] : null),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
