<?php

namespace App\Http\Resources\V1\Highlight;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HighlightResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'tenant_id' => $this->tenant_id,
            'label' => $this->label,
            'heading' => $this->heading,
            'body' => $this->body,
            'code' => $this->code,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'categories' => $this->whenLoaded('categories', fn () => $this->categories->map(fn ($category) => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
            ])->values()),
        ];
    }
}
