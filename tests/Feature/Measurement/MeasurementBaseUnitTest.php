<?php

namespace Tests\Feature\Measurement;

use App\Http\Middleware\ApiAuthenticate;
use App\Models\Measurement;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Specification;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\Shipping\UnitConverter;
use Database\Seeders\MeasurementSeeder;
use Database\Seeders\SpecificationSeeder;
use Illuminate\Support\Str;

function baseUnitTenant(): Tenant
{
    return Tenant::create([
        'name' => 'Unit Tenant',
        'slug' => 'unit-tenant-'.Str::lower(Str::random(6)),
        'is_active' => true,
    ]);
}

function baseUnitMeasurement(Tenant $tenant, string $code, array $units, string $baseCode): array
{
    $measurement = Measurement::create([
        'tenant_id' => $tenant->id,
        'name' => Str::headline($code),
        'code' => $code,
        'is_active' => true,
    ]);

    $created = [];

    foreach ($units as $unitCode => $factor) {
        $created[$unitCode] = Unit::create([
            'tenant_id' => $tenant->id,
            'measurement_id' => $measurement->id,
            'name' => Str::headline($unitCode),
            'code' => $unitCode,
            'symbol' => $unitCode,
            'factor' => $factor,
            'offset' => 0,
            'precision' => 2,
            'is_active' => true,
        ]);
    }

    $measurement->update(['base_unit_id' => $created[$baseCode]->id]);

    return [$measurement->refresh(), $created];
}

it('seeds an explicit base unit for seeded measurements', function () {
    $tenant = baseUnitTenant();

    $this->seed(MeasurementSeeder::class);

    $length = Measurement::query()
        ->where('tenant_id', $tenant->id)
        ->where('code', 'length')
        ->firstOrFail();

    expect($length->base_unit_id)->not->toBeNull();
    expect($length->baseUnit)->not->toBeNull();
    expect($length->baseUnit->code)->toBe('m');

    $packaging = Measurement::query()
        ->where('tenant_id', $tenant->id)
        ->where('code', 'packaging')
        ->firstOrFail();

    expect($packaging->baseUnit?->code)->toBe('pc');
});

it('prefers the requested target code over the declared base unit', function () {
    $tenant = baseUnitTenant();

    [$measurement, $units] = baseUnitMeasurement($tenant, 'length', [
        'm' => 1,
        'cm' => 0.01,
        'mm' => 0.001,
    ], 'm');

    expect($measurement->baseUnit->code)->toBe('m');
    expect(UnitConverter::toCentimetres(2.0, $units['m']->id))->toBe(200.0);
});

it('falls back to the declared base unit when the target code is absent', function () {
    $tenant = baseUnitTenant();

    [, $units] = baseUnitMeasurement($tenant, 'mass', [
        'g' => 1,
        'mg' => 0.001,
    ], 'g');

    // `kg` is not in this catalogue, so the base unit (g) is the normalisation target.
    expect(UnitConverter::toKilograms(2500.0, $units['mg']->id))->toBe(2.5);
});

it('backfills specification unit references from the seeded catalogue', function () {
    baseUnitTenant();

    $this->seed(MeasurementSeeder::class);
    $this->seed(SpecificationSeeder::class);

    $length = Specification::query()->where('slug', 'length')->firstOrFail();

    expect($length->unit)->toBe('mm');
    expect($length->unit_id)->not->toBeNull();
    expect($length->unitOfMeasure?->code)->toBe('mm');
});

it('exposes the base unit over the API', function () {
    config()->set('auth.guards.api', [
        'driver' => 'session',
        'provider' => 'users',
    ]);

    $this->withoutMiddleware(ApiAuthenticate::class);

    $user = User::factory()->create();
    $tenant = baseUnitTenant();

    $organization = Organization::create([
        'tenant_id' => $tenant->id,
        'name' => 'Unit Vendor',
        'slug' => 'unit-vendor-'.Str::lower(Str::random(6)),
        'type' => 'vendor',
        'is_active' => true,
    ]);

    Membership::create([
        'tenant_id' => $tenant->id,
        'organization_id' => $organization->id,
        'user_id' => $user->id,
        'role' => 'owner',
        'is_primary' => true,
        'is_active' => true,
    ]);

    $this->actingAs($user, 'api');

    $measurement = Measurement::create([
        'tenant_id' => $tenant->id,
        'name' => 'Length',
        'code' => 'length',
        'is_active' => true,
    ]);

    $metre = Unit::create([
        'tenant_id' => $tenant->id,
        'measurement_id' => $measurement->id,
        'name' => 'Metre',
        'code' => 'm',
        'symbol' => 'm',
        'factor' => 1,
        'offset' => 0,
        'precision' => 2,
        'is_active' => true,
    ]);

    $this->getJson('/api/v1/measurements/'.$measurement->uuid)
        ->assertOk()
        ->assertJsonPath('data.base_unit_id', null);

    $this->putJson('/api/v1/measurements/'.$measurement->uuid, [
        'base_unit_id' => $metre->id,
    ])
        ->assertOk()
        ->assertJsonPath('data.base_unit_id', $metre->id)
        ->assertJsonPath('data.base_unit.code', 'm');
});
