<?php

namespace Tests\Feature\Specification;

use App\Models\Specification;
use App\Models\User;

beforeEach(function () {
    Specification::create([
        'name' => 'Operating Voltage',
        'slug' => 'operating-voltage',
        'type' => 'product',
        'group' => 'Electrical',
        'unit' => 'v',
        'description' => 'Nominal operating voltage',
        'sort_order' => 20,
    ]);

    Specification::create([
        'name' => 'Filter Size',
        'slug' => 'filter-size',
        'type' => 'variant',
        'group' => 'Dimensions',
        'sort_order' => 1,
    ]);

    Specification::create([
        'name' => 'Length',
        'slug' => 'length',
        'type' => 'product',
        'group' => 'Dimensions',
        'unit' => 'mm',
        'description' => 'Overall length end to end',
        'sort_order' => 11,
    ]);

    $this->actingAs(User::factory()->create(), 'api');
});

it('lists the product specification catalogue grouped and ordered', function () {
    $response = $this->getJson('/api/v1/specifications')->assertOk();

    expect($response->json('data'))->toHaveCount(2);
    expect(collect($response->json('data'))->pluck('name')->all())
        ->toBe(['Length', 'Operating Voltage']);
});

it('exposes the suggested unit and description', function () {
    $response = $this->getJson('/api/v1/specifications')->assertOk();

    $operatingVoltage = collect($response->json('data'))->firstWhere('name', 'Operating Voltage');

    expect($operatingVoltage)->toMatchArray([
        'slug' => 'operating-voltage',
        'group' => 'Electrical',
        'unit' => 'v',
        'description' => 'Nominal operating voltage',
    ]);
});

it('filters by specification type', function () {
    $response = $this->getJson('/api/v1/specifications?type=variant')->assertOk();

    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.name'))->toBe('Filter Size');
});
