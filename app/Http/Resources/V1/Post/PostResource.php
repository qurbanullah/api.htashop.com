<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Post;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            'content' => $this->content,
            'featured_image' => $this->featured_image,
            'featured_image_url' => $this->featured_image ? (
                str_starts_with((string) $this->featured_image, 'http')
                    ? (string) $this->featured_image
                    : config('app.cdn_url', 'https://cdn.htashop.com') . '/' . ltrim((string) $this->featured_image, '/')
            ) : null,
            'images' => $this->images ?? [],
            'type' => $this->type?->value,
            'type_label' => $this->type?->label(),
            'metadata' => $this->metadata ?? [],
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'is_published_as_blog' => $this->is_published_as_blog,
            'scheduled_at' => $this->scheduled_at?->toISOString(),
            'sent_at' => $this->sent_at?->toISOString(),
            'recipients' => $this->recipients ?? [],
            'recipients_count' => $this->recipients_count ?? 0,
            'sent_count' => $this->sent_count ?? 0,
            'opened_count' => $this->opened_count ?? 0,
            'clicked_count' => $this->clicked_count ?? 0,
            'primary_category_id' => $this->primary_category_id,
            'tags' => $this->getTagsArray(),
            'categories' => $this->getCategoriesArray(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Creator relationship
            'creator' => $this->whenLoaded('creator', function () {
                return [
                    'id' => $this->creator->id,
                    'name' => $this->creator->name,
                    'email' => $this->creator->email,
                    'avatar' => $this->creator->avatar_small ?? $this->creator->avatar_url ?? null,
                ];
            }),

            // Primary category relationship
            'primary_category' => $this->whenLoaded('primaryCategory', function () {
                return $this->primaryCategory ? [
                    'id' => $this->primaryCategory->id,
                    'name' => $this->primaryCategory->name,
                    'slug' => $this->primaryCategory->slug,
                ] : null;
            }),

            // Statistics
            'stats' => $this->when($this->status?->value === 'sent', function () {
                $openRate = $this->sent_count > 0
                    ? round(($this->opened_count / $this->sent_count) * 100, 2)
                    : 0;
                $clickRate = $this->sent_count > 0
                    ? round(($this->clicked_count / $this->sent_count) * 100, 2)
                    : 0;

                return [
                    'open_rate' => $openRate,
                    'click_rate' => $clickRate,
                ];
            }),
        ];
    }

    /**
     * Get tags array from polymorphic relationship
     */
    private function getTagsArray(): array
    {
        // If tags relationship is loaded and is a collection
        if ($this->relationLoaded('tags') && is_object($this->tags) && method_exists($this->tags, 'map')) {
            return $this->tags->map(fn($tag) => $tag->name)->toArray();
        }

        // Otherwise return empty array
        return [];
    }

    /**
     * Get categories array from polymorphic relationship
     */
    private function getCategoriesArray(): array
    {
        // If categories relationship is loaded and is a collection
        if ($this->relationLoaded('categories') && is_object($this->categories) && method_exists($this->categories, 'map')) {
            return $this->categories->map(fn($category) => $category->name)->toArray();
        }

        // Otherwise return empty array
        return [];
    }
}
