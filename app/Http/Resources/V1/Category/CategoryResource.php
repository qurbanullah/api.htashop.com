<?php

namespace App\Http\Resources\V1\Category;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'parent_id' => $this->parent_id,
            'level' => $this->level,
            'sort_order' => $this->sort_order,
            'children' => $this->whenLoaded('children', fn () => static::collection($this->children)),
        ];
    }
}
