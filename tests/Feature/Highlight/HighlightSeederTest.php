<?php

namespace Tests\Feature\Highlight;

use App\Models\Category;
use App\Services\Highlight\HighlightService;
use Database\Seeders\CategorySeeder;
use Database\Seeders\HighlightSeeder;

function availableHighlightsFor(int $categoryId): int
{
    return app(HighlightService::class)
        ->available([
            'category_ids' => [$categoryId],
            'only_active' => true,
            'per_page' => 200,
        ])
        ->total();
}

beforeEach(function () {
    $this->seed(CategorySeeder::class);
    $this->seed(HighlightSeeder::class);
});

it('seeds highlights for every root category branch', function () {
    $roots = Category::query()->whereNull('parent_id')->get(['id', 'slug', 'name']);

    expect($roots)->not->toBeEmpty();

    $missing = $roots
        ->reject(fn (Category $root): bool => availableHighlightsFor($root->id) > 0)
        ->pluck('slug')
        ->all();

    expect($missing)->toBe([]);
});

it('surfaces branch highlights for a deep sub-category', function () {
    $leaf = Category::query()->where('slug', 'laptops-notebooks')->firstOrFail();

    expect(availableHighlightsFor($leaf->id))->toBeGreaterThan(0);
});
