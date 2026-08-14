<?php

namespace App\Http\Resources\V1\Comment;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
            'read_at' => $this->read_at?->toISOString(),

            // Author info
            'author' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                    'avatar' => $this->user->avatar_url ?? null,
                ];
            }),

            // Parent comment (for threading)
            'parent_id' => $this->parent_id,
            'parent' => $this->when($this->parent_id && $this->relationLoaded('parent'), function () {
                return new CommentResource($this->parent);
            }),

            // Replies
            'replies' => CommentResource::collection($this->whenLoaded('replies')),
            'replies_count' => $this->when(isset($this->replies_count), $this->replies_count),

            // Revision contexts (for polymorphic many-to-many relationship)
            'revisions' => $this->whenLoaded('revisions', function () {
                return $this->revisions->map(function ($revision) {
                    return [
                        'id' => $revision->id,
                        'version_number' => $revision->version_number,
                        'revision_type' => $revision->revision_type,
                        'status' => $revision->status,
                    ];
                });
            }),

            // Commentable entity (reviewer or manuscript)
            'commentable_type' => $this->commentable_type,
            'commentable_id' => $this->commentable_id,

            'attachments' => $this->attachments,
            'metadata' => $this->metadata,

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
