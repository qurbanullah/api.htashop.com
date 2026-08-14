<?php

namespace App\Http\Resources\V1\Tutorial;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TutorialResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Generate frontend URL
        $frontendBase = config('app.frontend_url', 'https://volvicon.com');
        $slug = $this->slug ?? $this->uuid;
        $frontendUrl = rtrim($frontendBase, '/') . '/tutorials/' . $slug;

        $assets = $this->whenLoaded('dams', function () {
            $mapAsset = function (string $collection) {
                $asset = $this->dams
                    ->where('collection_name', $collection)
                    ->firstWhere('is_current', true);

                if (! $asset) {
                    return null;
                }

                return [
                    'id' => $asset->id,
                    'uuid' => $asset->uuid,
                    'collection_name' => $asset->collection_name,
                    'file_name' => $asset->file_name,
                    'object_key' => $asset->object_key,
                    'mime_type' => $asset->mime_type,
                    'size' => $asset->size,
                    'etag' => $asset->etag,
                    'metadata' => $asset->metadata,
                ];
            };

            return [
                'thumbnail' => $mapAsset('thumbnail'),
                'video_file' => $mapAsset('video_file'),
            ];
        });

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'type' => $this->type->value ?? $this->type,
            'type_label' => $this->type->label() ?? $this->type,
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            'content' => $this->content,
            'thumbnail' => $this->thumbnail,
            'video_file' => $this->video_file,
            'youtube_url' => $this->youtube_url,
            'video_source' => $this->getVideoSource(),
            'has_video' => $this->hasVideo(),
            'duration' => $this->duration,
            'duration_formatted' => $this->duration ? $this->formatDuration($this->duration) : null,
            'difficulty_level' => $this->difficulty_level,
            'status' => $this->status->value ?? $this->status,
            'status_label' => $this->status->label() ?? $this->status,
            'views_count' => $this->views_count,
            'likes_count' => $this->likes_count,
            'published_at' => $this->published_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            'metadata' => $this->metadata,
            'assets' => $assets,
            'frontend_url' => $frontendUrl,
            'primary_category_id' => $this->primary_category_id,

            // Relationships
            'primary_category' => $this->whenLoaded('primaryCategory', function () {
                return $this->primaryCategory ? [
                    'id' => $this->primaryCategory->id,
                    'name' => $this->primaryCategory->name,
                    'slug' => $this->primaryCategory->slug,
                ] : null;
            }),
            'creator' => $this->whenLoaded('creator', function () {
                return [
                    'id' => $this->creator->id,
                    'uuid' => $this->creator->uuid,
                    'name' => $this->creator->name,
                    'email' => $this->creator->email,
                    'avatar' => $this->creator->avatar,
                ];
            }),
            'tags' => $this->whenLoaded('tags', function () {
                return $this->tags->map(function ($tag) {
                    return [
                        'id' => $tag->id,
                        'name' => $tag->name,
                        'slug' => $tag->slug,
                    ];
                });
            }),
            'categories' => $this->whenLoaded('categories', function () {
                return $this->categories->map(function ($category) {
                    return [
                        'id' => $category->id,
                        'name' => $category->name,
                        'slug' => $category->slug,
                    ];
                });
            }),
        ];
    }

    /**
     * Format duration in human-readable format
     */
    protected function formatDuration(int $seconds): string
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $seconds = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $seconds);
        }

        return sprintf('%d:%02d', $minutes, $seconds);
    }
}
