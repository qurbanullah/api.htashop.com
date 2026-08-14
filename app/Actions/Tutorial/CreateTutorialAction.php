<?php

declare(strict_types=1);

namespace App\Actions\Tutorial;

use App\Models\Tutorial;
use App\Models\Tag;
use App\Models\Category;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * CRUD operation class for Tutorial Model - Create operation.
 *
 * This class follows SOLID principles:
 * - Single Responsibility: Only handles creation of tutorials
 * - Open/Closed: Can be extended without modification
 * - Liskov Substitution: Can be replaced by any implementation
 * - Interface Segregation: Focused interface
 * - Dependency Inversion: Depends on abstractions (Model interface)
 */
class CreateTutorialAction
{
    /**
     * Validate and create tutorial record in database.
     *
     * @param  array  $data  tutorial values
     */
    public function handle(array $data): Tutorial
    {
        return DB::transaction(function () use ($data) {
            // Generate unique slug from title
            $slug = $this->generateUniqueSlug(data_get($data, 'title'));

            // Auto-set published_at if status is published and published_at not provided
            $status = data_get($data, 'status', 'draft');
            $publishedAt = data_get($data, 'published_at');
            if ($status === 'published' && !$publishedAt) {
                $publishedAt = now();
            }

            // Create the tutorial record
            $tutorial = Tutorial::create([
                'uuid' => data_get($data, 'uuid', Str::uuid()),
                'title' => data_get($data, 'title'),
                'slug' => $slug,
                'type' => data_get($data, 'type', 'video'),
                'excerpt' => data_get($data, 'excerpt'),
                'content' => data_get($data, 'content'),
                'thumbnail' => data_get($data, 'thumbnail'),
                'video_file' => data_get($data, 'video_file'),
                'youtube_url' => data_get($data, 'youtube_url'),
                'duration' => data_get($data, 'duration'),
                'difficulty_level' => data_get($data, 'difficulty_level'),
                'status' => $status,
                'published_at' => $publishedAt,
                'created_by' => data_get($data, 'created_by'),
                'primary_category_id' => data_get($data, 'primary_category_id'),
                'views_count' => data_get($data, 'views_count', 0),
                'likes_count' => data_get($data, 'likes_count', 0),
                'metadata' => data_get($data, 'metadata', []),
            ]);

            // Attach tags if provided (polymorphic relation)
            if (isset($data['tags']) && is_array($data['tags'])) {
                $tagIds = $this->resolveTagIds($data['tags']);
                $tutorial->tags()->sync($tagIds);
            }

            // Attach categories if provided (polymorphic relation)
            if (isset($data['categories']) && is_array($data['categories'])) {
                $categoryIds = $this->resolveCategoryIds($data['categories']);
                $tutorial->categories()->sync($categoryIds);

                // Auto-set primary category if not specified but categories are provided
                if (empty($data['primary_category_id']) && !empty($categoryIds)) {
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
    protected function generateUniqueSlug(string $title): string
    {
        $slug = Str::slug($title);
        $originalSlug = $slug;
        $count = 1;

        while (Tutorial::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $count;
            $count++;
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
                // Find or create tag by name (with slug)
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
                // Find or create category by name (with slug)
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
