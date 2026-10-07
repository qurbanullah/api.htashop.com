<?php

use App\Models\Category;
use Database\Seeders\CategorySeeder;

it('seeds the robotics category tree', function () {
    $this->seed(CategorySeeder::class);

    $robotics = Category::query()->where('slug', 'robotics-automation')->first();

    expect($robotics)->not->toBeNull()
        ->and($robotics->code)->toBe('RB')
        ->and($robotics->is_active)->toBeTrue()
        ->and($robotics->children()->count())->toBe(10);

    $motors = Category::query()->where('slug', 'motors-actuators')->first();
    expect($motors)->not->toBeNull()
        ->and($motors->parent_id)->toBe($robotics->id);

    $drones = Category::query()->where('slug', 'drone-uav-parts')->first();
    expect($drones)->not->toBeNull()
        ->and($drones->parent_id)->toBe($robotics->id);
});
