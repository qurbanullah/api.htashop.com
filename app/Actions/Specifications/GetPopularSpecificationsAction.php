<?php

namespace App\Actions\Specifications;

use App\Models\Specification;
use Illuminate\Support\Collection;

class GetPopularSpecificationsAction
{
    public function execute(string $type = 'product', int $limit = 20): Collection
    {
        return Specification::where('type', $type)
            ->orderBy('usage_count', 'desc')
            ->orderBy('group')
            ->limit($limit)
            ->get();
    }
}
