<?php

namespace App\Http\Resources\V1\Label;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LabelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'tenant_id' => $this->tenant_id,
            'scope' => $this->isSystem() ? 'system' : 'tenant',
            'slug' => $this->slug,
            'display_name' => $this->name,
            'name' => $this->name,
            'summary' => $this->summary,
            'description' => $this->description,
            'image' => $this->image,
            'sorting' => $this->sorting,
            'is_active' => (bool) $this->is_active,
            'metadata' => $this->metadata,
            'taxonomy_levels' => $this->metadata['taxonomy_levels'] ?? [],
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
