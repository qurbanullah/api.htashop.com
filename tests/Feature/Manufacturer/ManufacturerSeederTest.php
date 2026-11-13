<?php

namespace Tests\Feature\Manufacturer;

use App\Models\Manufacturer;
use Database\Seeders\ManufacturerSeeder;

it('seeds the OEM catalogue and the ISO 10261 record list', function () {
    $this->seed(ManufacturerSeeder::class);

    expect(Manufacturer::query()->count())->toBeGreaterThanOrEqual(800);

    $foxconn = Manufacturer::query()->where('slug', 'foxconn')->firstOrFail();
    expect($foxconn->type)->toBe('oem')
        ->and($foxconn->country)->toBe('Taiwan');

    $deere = Manufacturer::query()->where('slug', 'john-deere')->firstOrFail();
    expect($deere->code)->toBe('1RA')
        ->and($deere->country)->toBe('United States');

    $caterpillar = Manufacturer::query()->where('slug', 'caterpillar-inc')->firstOrFail();
    expect($caterpillar->code)->toBe('CAT')
        ->and($caterpillar->country)->toBe('United States');
});

it('keeps manufacturing brand links resolvable', function () {
    $this->seed(ManufacturerSeeder::class);

    foreach (['samsung', 'lg', 'sony', 'bosch', 'siemens', 'schneider-electric', 'abb', 'panasonic', 'philips', 'toshiba'] as $slug) {
        expect(Manufacturer::query()->where('slug', $slug)->exists())->toBeTrue();
    }
});

it('is idempotent', function () {
    $this->seed(ManufacturerSeeder::class);
    $count = Manufacturer::query()->count();

    $this->seed(ManufacturerSeeder::class);

    expect(Manufacturer::query()->count())->toBe($count);
});
