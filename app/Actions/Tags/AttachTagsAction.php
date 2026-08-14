<?php

namespace App\Actions\Tags;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use App\Models\Tag;

class AttachTagsAction
{
    /**
     * Attach tags to a taggable model
     *
     * @param Model $taggable The model to attach tags to (Product, Post, etc.)
     * @param array|Collection $tags Array of tag names or IDs
     * @param string $type Tag type (product, blog, etc.)
     * @return Collection The attached tags
     */
    public function execute(Model $taggable, array|Collection $tags, string $type = 'product'): Collection
    {
        $tags = is_array($tags) ? collect($tags) : $tags;

        $tagModels = $tags->map(function ($tag) use ($type) {
            if (is_numeric($tag)) {
                // If it's an ID, find the tag
                return Tag::find($tag);
            }

            // If it's a string, find or create the tag
            return Tag::firstOrCreate(
                ['slug' => Str::slug($tag), 'type' => $type],
                ['name' => $tag, 'usage_count' => 0]
            );
        })->filter(); // Remove any null values

        // Attach tags to the taggable model
        $taggable->tags()->syncWithoutDetaching($tagModels->pluck('id')->toArray());

        // Increment usage count for each tag
        $tagModels->each(function ($tag) {
            $tag->incrementUsage();
        });

        return $tagModels;
    }
}
