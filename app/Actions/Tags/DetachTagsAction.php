<?php

namespace App\Actions\Tags;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class DetachTagsAction
{
    /**
     * Detach tags from a taggable model
     *
     * @param Model $taggable The model to detach tags from
     * @param array|Collection|null $tags Tag IDs to detach (null = detach all)
     * @return void
     */
    public function execute(Model $taggable, array|Collection|null $tags = null): void
    {
        if ($tags === null) {
            // Detach all tags and decrement usage
            $existingTags = $taggable->tags;
            $taggable->tags()->detach();

            $existingTags->each(function ($tag) {
                $tag->decrementUsage();
            });

            return;
        }

        $tagIds = is_array($tags) ? $tags : $tags->toArray();

        // Get the tags that will be detached
        $tagsToDetach = $taggable->tags()->whereIn('tags.id', $tagIds)->get();

        // Detach the tags
        $taggable->tags()->detach($tagIds);

        // Decrement usage count
        $tagsToDetach->each(function ($tag) {
            $tag->decrementUsage();
        });
    }
}
