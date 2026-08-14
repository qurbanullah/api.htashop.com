<?php

namespace App\Actions\Tags;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use App\Models\Tag;

class SyncTagsAction
{
    /**
     * Sync tags for a taggable model (add new, remove old)
     *
     * @param Model $taggable The model to sync tags for
     * @param array|Collection $tags Array of tag names or IDs
     * @param string $type Tag type (product, blog, etc.)
     * @return Collection The synced tags
     */
    public function execute(Model $taggable, array|Collection $tags, string $type = 'product'): Collection
    {
        $tags = is_array($tags) ? collect($tags) : $tags;

        // Get current tags
        $currentTags = $taggable->tags;

        // Process new tags
        $newTagModels = $tags->map(function ($tag) use ($type) {
            if (is_numeric($tag)) {
                return Tag::find($tag);
            }

            return Tag::firstOrCreate(
                ['slug' => Str::slug($tag), 'type' => $type],
                ['name' => $tag, 'usage_count' => 0]
            );
        })->filter();

        $newTagIds = $newTagModels->pluck('id')->toArray();

        // Sync tags (this removes old and adds new)
        $taggable->tags()->sync($newTagIds);

        // Handle usage counts
        $removedTags = $currentTags->whereNotIn('id', $newTagIds);
        $addedTags = $newTagModels->whereNotIn('id', $currentTags->pluck('id'));

        // Decrement removed tags
        $removedTags->each(function ($tag) {
            $tag->decrementUsage();
        });

        // Increment added tags
        $addedTags->each(function ($tag) {
            $tag->incrementUsage();
        });

        return $newTagModels;
    }
}
