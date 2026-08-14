<?php

namespace App\Http\Resources\V1\Forum;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ForumTopicResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'icon' => $this->icon,
            'color' => $this->color,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'is_locked' => $this->is_locked,
            'post_count' => $this->post_count,
            'published_posts_count' => $this->when(
                isset($this->published_posts_count),
                $this->published_posts_count ?? 0
            ),
            'posts_count' => $this->when(
                isset($this->posts_count),
                $this->posts_count ?? 0
            ),
            'creator' => $this->when(
                $this->relationLoaded('creator') && $this->creator,
                fn () => [
                    'id' => $this->creator->id,
                    'name' => $this->creator->name,
                ]
            ),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
