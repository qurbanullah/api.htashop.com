<?php

namespace App\Http\Resources\V1\Category;

use App\Http\Resources\V1\Industry\IndustryResource;
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
            'code' => $this->code,
            'description' => $this->description,
            'parent_id' => $this->parent_id,
            'level' => $this->level,
            'path' => $this->path,
            'sort_order' => $this->sort_order,
            'is_active' => (bool) $this->is_active,
            'is_featured' => (bool) $this->is_featured,
            'featured_rank' => (int) $this->featured_rank,
            'icon' => $this->icon,
            'color' => $this->color,
            'image' => $this->image,
            'children_count' => $this->whenCounted('children'),
            'products_count' => $this->whenCounted('products'),
            'industries' => IndustryResource::collection($this->whenLoaded('industries')),
            'children' => $this->whenLoaded('children', fn () => static::collection($this->children)),
        ];
    }
}
