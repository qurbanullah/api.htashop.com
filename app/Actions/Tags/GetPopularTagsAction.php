<?php

namespace App\Actions\Tags;

use App\Models\Tag;
use Illuminate\Database\Eloquent\Collection;

class GetPopularTagsAction
{
    /**
     * Get popular tags by type and usage count
     *
     * @param string $type Tag type (product, blog, etc.)
     * @param int $limit Number of tags to return
     * @return Collection
     */
    public function execute(string $type = 'product', int $limit = 20): Collection
    {
        return Tag::where('type', $type)
            ->where('usage_count', '>', 0)
            ->orderBy('usage_count', 'desc')
            ->orderBy('name')
            ->limit($limit)
            ->get();
    }
}
