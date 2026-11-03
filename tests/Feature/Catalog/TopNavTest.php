<?php

namespace Tests\Feature\Catalog;

use App\Models\Category;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::flush();
    config(['catalog.top_nav.limit' => 6]);
});

it('serves only featured, active root categories ordered by featured rank', function () {
    $expected = [];

    foreach (range(1, 8) as $rank) {
        $category = Category::factory()->create([
            'name' => "Featured {$rank}",
            'slug' => "featured-{$rank}",
            'is_active' => true,
            'is_featured' => true,
            'featured_rank' => $rank,
            'sort_order' => 100 - $rank,
        ]);

        if ($rank <= 6) {
            $expected[] = $category->name;
        }
    }

    Category::factory()->create([
        'name' => 'Not featured',
        'slug' => 'not-featured',
        'is_featured' => false,
    ]);

    Category::factory()->create([
        'name' => 'Inactive featured',
        'slug' => 'inactive-featured',
        'is_active' => false,
        'is_featured' => true,
    ]);

    $parent = Category::query()->where('slug', 'featured-1')->firstOrFail();

    Category::factory()->create([
        'name' => 'Featured child',
        'slug' => 'featured-child',
        'is_featured' => true,
        'parent_id' => $parent->id,
    ]);

    $response = $this->getJson('/api/v1/catalog/top-nav')->assertOk();

    $names = collect($response->json('data'))->pluck('name')->all();

    expect($names)->toBe($expected);
});
