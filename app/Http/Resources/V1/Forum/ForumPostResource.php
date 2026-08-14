<?php

namespace App\Http\Resources\V1\Forum;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ForumPostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user('api');

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'slug' => $this->slug,
            'title' => $this->title,
            'body' => $this->body,
            'body_preview' => $this->when(
                !$request->routeIs('forum.posts.show', 'forum.posts.show-by-slug'),
                fn () => \Illuminate\Support\Str::limit(strip_tags($this->body), 280)
            ),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'is_pinned' => $this->is_pinned,
            'is_locked' => $this->is_locked,
            'is_featured' => $this->is_featured,
            'view_count' => $this->view_count,
            'like_count' => $this->like_count,
            'comment_count' => $this->comment_count,
            'visible_comments_count' => $this->when(
                isset($this->visible_comments_count),
                $this->visible_comments_count ?? 0
            ),
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
            'topic' => $this->when(
                $this->relationLoaded('topic') && $this->topic,
                fn () => [
                    'id' => $this->topic->id,
                    'name' => $this->topic->name,
                    'slug' => $this->topic->slug,
                    'color' => $this->topic->color,
                    'icon' => $this->topic->icon,
                ]
            ),
            'comments_count' => $this->when(
                isset($this->comments_count),
                $this->comments_count ?? 0
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
