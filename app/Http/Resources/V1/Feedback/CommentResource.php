<?php

namespace App\Http\Resources\V1\Feedback;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for Comment model
 * Transforms comment data for API responses
 */
class CommentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'content' => $this->content,
            'is_internal' => $this->is_internal,
            'is_read' => $this->is_read,
            'user' => [
                'id' => $this->user->id ?? null,
                'name' => $this->user->name ?? 'Unknown',
                'email' => $this->user->email ?? null,
                'avatar' => $this->user->avatar ?? null,
            ],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'metadata' => $this->metadata ?? [],
        ];
    }
}
