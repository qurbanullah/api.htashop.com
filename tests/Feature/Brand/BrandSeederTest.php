<?php

namespace Tests\Feature\Brand;

use App\Models\Brand;
use Database\Seeders\BrandSeeder;
use Database\Seeders\ManufacturerSeeder;

beforeEach(function () {
    $this->seed(ManufacturerSeeder::class);
});

it('seeds the ranked brands and the electronics catalogue', function () {
    $this->seed(BrandSeeder::class);

    expect(Brand::query()->count())->toBeGreaterThanOrEqual(700);

    $samsung = Brand::query()->where('slug', 'samsung')->firstOrFail();

    expect($samsung->name)->toBe('Samsung')
        ->and($samsung->country)->toBe('South Korea')
        ->and($samsung->origin)->toBe('admin')
        ->and($samsung->manufacturer?->name)->toBe('Samsung');

    expect(Brand::query()->where('slug', 'arduino')->value('country'))->toBe('Italy');
    expect(Brand::query()->where('slug', 'raspberry-pi')->exists())->toBeTrue();
    expect(Brand::query()->where('slug', 'texas-instruments')->exists())->toBeTrue();
});

it('normalises and de-duplicates names from the ranking', function () {
    $this->seed(BrandSeeder::class);

    // "Samsung Group" is normalised to "Samsung", so only one row exists.
    expect(Brand::query()->where('slug', 'samsung')->count())->toBe(1);
    expect(Brand::query()->where('slug', 'samsung-group')->exists())->toBeFalse();
    expect(Brand::query()->where('slug', 'the-home-depot')->value('name'))->toBe('The Home Depot');
    expect(Brand::query()->where('slug', 'deutsche-telekom')->value('name'))->toBe('Deutsche Telekom');
});

it('is idempotent', function () {
    $this->seed(BrandSeeder::class);
    $count = Brand::query()->count();

    $this->seed(BrandSeeder::class);

    expect(Brand::query()->count())->toBe($count);
});
