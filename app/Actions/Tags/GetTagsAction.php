<?php

namespace App\Actions\Tags;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class GetTagsAction
{
    /**
     * Get all tags for a taggable model
     *
     * @param  Model  $taggable  The model to get tags for
     */
    public function execute(Model $taggable): Collection
    {
        return $taggable->tags()->orderBy('name')->get();
    }
}
