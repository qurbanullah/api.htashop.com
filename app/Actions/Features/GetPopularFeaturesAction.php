<?php

namespace App\Actions\Features;

use App\Models\Feature;
use Illuminate\Support\Collection;

class GetPopularFeaturesAction
{
    public function execute(string $type = 'product', int $limit = 20): Collection
    {
        return Feature::where('type', $type)
            ->orderBy('usage_count', 'desc')
            ->limit($limit)
            ->get();
    }
}
