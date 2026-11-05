<?php

namespace Tests\Feature\Feature;

use App\Models\Category;
use App\Models\Feature;
use App\Models\User;

beforeEach(function () {
    $this->root = Category::factory()->create([
        'name' => 'Electronics',
        'slug' => 'electronics',
        'code' => 'EC',
    ]);

    $this->child = Category::factory()->create([
        'name' => 'Sensors & Transducers',
        'slug' => 'sensors-transducers',
        'parent_id' => $this->root->id,
    ]);

    $this->recommended = Feature::create([
        'name' => 'ESD Safe',
        'slug' => 'esd-safe',
        'is_active' => true,
    ]);
    $this->recommended->categories()->attach($this->root->id);

    Feature::create(['name' => 'Gluten Free', 'slug' => 'gluten-free', 'is_active' => true]);
    Feature::create(['name' => 'Retired Feature', 'slug' => 'retired-feature', 'is_active' => false]);

    $this->actingAs(User::factory()->create(), 'api');
});

it('returns every active feature and flags the ones recommended for the category', function () {
    $response = $this->getJson('/api/v1/features?category_id='.$this->child->id)->assertOk();

    $rows = collect($response->json('data'))->keyBy('name');

    expect($rows)->toHaveCount(2)
        ->and($rows->has('Retired Feature'))->toBeFalse()
        ->and($rows['ESD Safe']['matches_category'])->toBeTrue()
        ->and($rows['Gluten Free']['matches_category'])->toBeFalse();
});

it('resolves recommendations through the top-level parent', function () {
    $response = $this->getJson('/api/v1/features?category_id='.$this->child->id)->assertOk();

    $recommended = collect($response->json('data'))->firstWhere('name', 'ESD Safe');

    expect($recommended)->not->toBeNull()
        ->and($recommended['matches_category'])->toBeTrue();
});

it('returns the full vocabulary when no category is supplied', function () {
    $response = $this->getJson('/api/v1/features')->assertOk();

    expect($response->json('data'))->toHaveCount(2);
    expect(collect($response->json('data'))->every(fn (array $row) => $row['matches_category'] === false))
        ->toBeTrue();
});
