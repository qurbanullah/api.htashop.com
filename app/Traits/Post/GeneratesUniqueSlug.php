<?php

namespace App\Traits\Post;

use App\Models\Post;
use Illuminate\Support\Str;

trait GeneratesUniqueSlug
{
    /**
     * Generate a unique slug for the post.
     * Uses a random 6-character suffix to ensure uniqueness.
     */
    protected function generateUniqueSlug(string $title, ?int $excludeId = null): string
    {
        $baseSlug = Str::slug($title);
        $slug = $baseSlug;

        // Check if base slug is unique (excluding the current post if updating)
        $query = Post::where('slug', $slug);
        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        // If base slug is already unique, return it
        if (!$query->exists()) {
            return $slug;
        }

        // Generate a unique slug with random suffix
        $maxAttempts = 10;
        for ($i = 0; $i < $maxAttempts; $i++) {
            $randomSuffix = Str::lower(Str::random(6));
            $slug = $baseSlug . '-' . $randomSuffix;

            $checkQuery = Post::where('slug', $slug);
            if ($excludeId !== null) {
                $checkQuery->where('id', '!=', $excludeId);
            }

            if (!$checkQuery->exists()) {
                return $slug;
            }
        }

        // Fallback: use timestamp + random if all attempts fail
        return $baseSlug . '-' . time() . '-' . Str::lower(Str::random(4));
    }

    /**
     * Check if slug should be regenerated when updating
     */
    protected function shouldRegenerateSlug(Post $post, array $data): bool
    {
        $newTitle = data_get($data, 'title');

        // Only regenerate if title changed and slug wasn't explicitly provided
        return $newTitle !== null
            && $newTitle !== $post->title;
    }
}
