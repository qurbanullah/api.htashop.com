<?php

declare(strict_types=1);

namespace App\Actions\Tutorial;

use App\Models\Tutorial;
use App\Models\Tag;
use App\Models\Category;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * CRUD operation class for Tutorial Model - Update operation.
 *
 * This class follows SOLID principles:
 * - Single Responsibility: Only handles updating of tutorials
 */
class UpdateTutorialAction
{
    /**
     * Update tutorial record in database.
     *
     * @param  Tutorial  $tutorial  tutorial instance to update
     * @param  array  $data  update values
     */
    public function handle(Tutorial $tutorial, array $data): Tutorial
    {
        return DB::transaction(function () use ($tutorial, $data) {
            // Update slug if title changed
            if (isset($data['title']) && $data['title'] !== $tutorial->title) {
                $data['slug'] = $this->generateUniqueSlug($data['title'], $tutorial->id);
            }

            // Auto-set published_at if status is being changed to published
            $newStatus = data_get($data, 'status', $tutorial->status);
            $publishedAt = data_get($data, 'published_at', $tutorial->published_at);

            // If changing to published status and no published_at is set, use current time
            if ($newStatus === 'published' && !$publishedAt) {
                $publishedAt = now();
            }
            // If changing away from published status, clear published_at
            elseif ($newStatus !== 'published' && $tutorial->status === 'published') {
                $publishedAt = null;
            }

            // Update the tutorial record
            $tutorial->update([
                'title' => data_get($data, 'title', $tutorial->title),
                'slug' => data_get($data, 'slug', $tutorial->slug),
                'type' => data_get($data, 'type', $tutorial->type),
                'excerpt' => data_get($data, 'excerpt', $tutorial->excerpt),
                'content' => data_get($data, 'content', $tutorial->content),
                'thumbnail' => data_get($data, 'thumbnail', $tutorial->thumbnail),
                'video_file' => data_get($data, 'video_file', $tutorial->video_file),
                'youtube_url' => data_get($data, 'youtube_url', $tutorial->youtube_url),
                'duration' => data_get($data, 'duration', $tutorial->duration),
                'difficulty_level' => data_get($data, 'difficulty_level', $tutorial->difficulty_level),
                'status' => $newStatus,
                'published_at' => $publishedAt,
                'primary_category_id' => data_get($data, 'primary_category_id', $tutorial->primary_category_id),
                'metadata' => data_get($data, 'metadata', $tutorial->metadata),
            ]);

            // Update tags if provided
            if (isset($data['tags'])) {
                $tagIds = $this->resolveTagIds($data['tags']);
                $tutorial->tags()->sync($tagIds);
            }

            // Update categories if provided
            if (isset($data['categories'])) {
                $categoryIds = $this->resolveCategoryIds($data['categories']);
                $tutorial->categories()->sync($categoryIds);

                // Auto-set primary category if not specified but categories are provided
                if (empty($data['primary_category_id']) && !empty($categoryIds) && empty($tutorial->primary_category_id)) {
                    $tutorial->primary_category_id = $categoryIds[0];
                    $tutorial->save();
                }
            }

            return $tutorial->fresh(['creator', 'primaryCategory', 'tags', 'categories']);
        });
    }

    /**
     * Generate a unique slug from title
     */
    protected function generateUniqueSlug(string $title, ?int $excludeId = null): string
    {
        $slug = Str::slug($title);
        $originalSlug = $slug;
        $count = 1;

        $query = Tutorial::where('slug', $slug);
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        while ($query->exists()) {
            $slug = $originalSlug . '-' . $count;
            $count++;
            $query = Tutorial::where('slug', $slug);
            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }
        }

        return $slug;
    }

    /**
     * Resolve tag IDs from array of tag IDs or names
     */
    protected function resolveTagIds(array $tags): array
    {
        $tagIds = [];

        foreach ($tags as $tag) {
            if (is_numeric($tag)) {
                $tagIds[] = $tag;
            } else {
                $tagModel = Tag::firstOrCreate(
                    ['name' => $tag],
                    ['slug' => Str::slug($tag)]
                );
                $tagIds[] = $tagModel->id;
            }
        }

        return $tagIds;
    }

    /**
     * Resolve category IDs from array of category IDs or names
     */
    protected function resolveCategoryIds(array $categories): array
    {
        $categoryIds = [];

        foreach ($categories as $category) {
            if (is_numeric($category)) {
                $categoryIds[] = $category;
            } else {
                $categoryModel = Category::firstOrCreate(
                    ['name' => $category],
                    ['slug' => Str::slug($category)]
                );
                $categoryIds[] = $categoryModel->id;
            }
        }

        return $categoryIds;
    }
}
