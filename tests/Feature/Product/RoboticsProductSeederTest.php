<?php

use App\Enums\Sourcing;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Tenant;
use Database\Seeders\CategorySeeder;
use Database\Seeders\RoboticsProductSeeder;
use Illuminate\Support\Str;

it('seeds the robotics catalog with sourcing and categories', function () {
    $tenant = Tenant::query()->create([
        'name' => 'HTAShop',
        'slug' => 'rb-'.Str::lower(Str::random(8)),
        'is_active' => true,
    ]);
    Organization::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'HTAShop Store',
        'slug' => 'rb-org-'.Str::lower(Str::random(8)),
    ]);

    $this->seed(CategorySeeder::class);
    $this->seed(RoboticsProductSeeder::class);

    expect(Product::query()->count())->toBe(90);

    $motor = Product::query()->where('slug', 'nema-17-stepper-motor')->first();

    expect($motor)->not->toBeNull()
        ->and($motor->sourcing)->toBe(Sourcing::ON_DEMAND)
        ->and($motor->origin_country)->toBe('CN')
        ->and($motor->hs_code)->toBe('8501')
        ->and($motor->lead_time_days)->toBe(18)
        ->and((float) data_get($motor->metadata, 'price'))->toBe(12.0)
        ->and($motor->categories()->pluck('slug')->contains('motors-actuators'))->toBeTrue();
});
