<?php

namespace App\Http\Resources\V1\Forum;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ForumCommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user('api');

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'body' => $this->body,
            'status' => $this->status->value,
            'like_count' => $this->like_count,
            'parent_id' => $this->parent_id,
            'is_liked' => $this->when($user !== null, fn () => $this->isLikedBy($user)),
            'is_own' => $this->when($user !== null, fn () => $user->id === $this->user_id),
            'author' => $this->when(
                $this->relationLoaded('author') && $this->author,
                fn () => [
                    'id' => $this->author->id,
                    'name' => $this->author->name ?? trim(($this->author->first_name ?? '') . ' ' . ($this->author->last_name ?? '')),
                    'avatar_url' => $this->author->avatar_small ?? null,
                ]
            ),
            'post' => $this->when(
                $this->relationLoaded('post') && $this->post,
                fn () => [
                    'id' => $this->post->id,
                    'uuid' => $this->post->uuid,
                    'title' => $this->post->title,
                    'slug' => $this->post->slug,
                ]
            ),
            'replies' => $this->when(
                $this->relationLoaded('visibleReplies'),
                fn () => self::collection($this->visibleReplies)
            ),
            'visible_replies_count' => $this->when(
                isset($this->visible_replies_count),
                $this->visible_replies_count ?? 0
            ),
            'replies_count' => $this->when(
                isset($this->replies_count),
                $this->replies_count ?? 0
            ),
            'likes_count' => $this->when(
                isset($this->likes_count),
                $this->likes_count ?? 0
            ),
            'reports_count' => $this->when(
                isset($this->reports_count),
                $this->reports_count ?? 0
            ),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
