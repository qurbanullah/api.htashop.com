<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

/**
 * Curates the storefront top navigation.
 *
 * `is_featured` marks the pool the nav may draw from; `featured_rank` sets the
 * priority (lower first). The storefront shows at most
 * `catalog.top_nav.limit` (6 by default), so any extra featured categories stay
 * in the pool for later rotation without appearing in the nav.
 */
class CategoryTopNavSeeder extends Seeder
{
    public function run(): void
    {
        // The curated nav order. Rank is 1-based and explicit so the storefront
        // order never depends on category ids or names.
        $featured = [
            'electronics',
            'information-technology',
            'robotics-automation',
            'electrical-lighting',
            'mro-industrial',
            'safety-ppe',
        ];

        Category::query()->whereNull('parent_id')->update([
            'is_featured' => false,
            'featured_rank' => 0,
        ]);

        foreach ($featured as $index => $slug) {
            Category::query()
                ->whereNull('parent_id')
                ->where('slug', $slug)
                ->update([
                    'is_featured' => true,
                    'featured_rank' => $index + 1,
                ]);
        }

        Cache::forget('catalog:top-nav:'.(int) config('catalog.top_nav.limit', 6));

        $this->command->info('Top navigation categories flagged.');
    }
}
