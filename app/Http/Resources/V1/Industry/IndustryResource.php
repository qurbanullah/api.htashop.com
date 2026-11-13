<?php

namespace App\Http\Resources\V1\Industry;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IndustryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'parent_id' => $this->parent_id,
            'sort_order' => $this->sort_order,
            'is_active' => (bool) $this->is_active,
            'parent' => $this->whenLoaded('parent', fn (): array => [
                'id' => $this->parent->id,
                'code' => $this->parent->code,
                'name' => $this->parent->name,
            ]),
            'quote_requests_count' => $this->whenCounted('quoteRequests'),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
