<?php

namespace Tests\Feature\Shipping;

use App\Http\Middleware\ApiAuthenticate;
use App\Models\City;
use App\Models\Country;
use App\Models\Measurement;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\PackagingTemplate;
use App\Models\ShippingZone;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\MeasurementSeeder;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    config()->set('auth.guards.api', [
        'driver' => 'session',
        'provider' => 'users',
    ]);

    $this->withoutMiddleware(ApiAuthenticate::class);

    $this->user = User::factory()->create();

    $this->tenant = Tenant::create([
        'name' => 'Acme Tenant',
        'slug' => 'acme-tenant',
        'is_active' => true,
    ]);

    $this->organization = Organization::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Acme Vendor',
        'slug' => 'acme-vendor',
        'type' => 'vendor',
        'is_active' => true,
    ]);

    Membership::create([
        'tenant_id' => $this->tenant->id,
        'organization_id' => $this->organization->id,
        'user_id' => $this->user->id,
        'role' => 'owner',
        'is_primary' => true,
        'is_active' => true,
    ]);

    $this->seed(MeasurementSeeder::class);

    Role::findOrCreate('admin', 'api');
    Role::findOrCreate('super-admin', 'api');

    $this->actingAs($this->user, 'api');
});

function shipCfgUnit(string $measurement, string $code): int
{
    $measurementId = (int) Measurement::query()->where('code', $measurement)->value('id');

    return (int) Unit::query()
        ->where('measurement_id', $measurementId)
        ->where('code', $code)
        ->value('id');
}

function shipCfgCountry(string $code = 'PK'): Country
{
    return Country::query()->firstOrCreate(
        ['code' => $code],
        ['name' => 'Pakistan', 'code_alpha3' => 'PAK'],
    );
}

it('creates a packaging template and normalises its units', function () {
    $response = $this->postJson('/api/v1/packaging-templates', [
        'name' => 'Medium Carton',
        'code' => 'medium-carton',
        'packaging_type_unit_id' => shipCfgUnit('packaging', 'box'),
        'length' => 20,
        'width' => 15,
        'height' => 10,
        'dimension_unit_id' => shipCfgUnit('length', 'in'),
        'max_weight' => 22,
        'weight_unit_id' => shipCfgUnit('mass', 'lb'),
        'tare_weight' => 300,
        'tare_weight_unit_id' => shipCfgUnit('mass', 'g'),
        'is_active' => true,
    ])
        ->assertCreated()
        ->assertJsonPath('message', 'Packaging template created successfully');

    $data = $response->json('data');

    expect($data['tenant_id'])->toBe($this->tenant->id)
        ->and($data['packaging_type']['code'])->toBe('box')
        ->and($data['length_cm'])->toBe(50.8)
        ->and($data['width_cm'])->toBe(38.1)
        ->and($data['height_cm'])->toBe(25.4)
        // 22 lb × 0.45359237
        ->and($data['max_weight_kg'])->toBe(9.979)
        ->and($data['tare_weight_kg'])->toBe(0.3)
        ->and(round((float) $data['volume_cm3'], 2))->toBe(49161.19);
});

it('rejects units that belong to the wrong measurement', function () {
    $this->postJson('/api/v1/packaging-templates', [
        'name' => 'Bad Box',
        'dimension_unit_id' => shipCfgUnit('mass', 'kg'),
        'weight_unit_id' => shipCfgUnit('length', 'cm'),
        'packaging_type_unit_id' => shipCfgUnit('mass', 'kg'),
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors([
            'dimension_unit_id',
            'weight_unit_id',
            'packaging_type_unit_id',
        ]);
});

it('creates a zone with country and city matchers', function () {
    $country = shipCfgCountry();
    $city = City::create(['country_id' => $country->id, 'name' => 'Lahore']);

    $response = $this->postJson('/api/v1/shipping-zones', [
        'name' => 'Lahore Metro',
        'code' => 'lahore-metro',
        'locations' => [
            ['type' => 'country', 'country_id' => $country->id],
            // Country is derived from the city when omitted.
            ['type' => 'city', 'city_id' => $city->id],
        ],
    ])
        ->assertCreated()
        ->assertJsonPath('message', 'Shipping zone created successfully');

    $locations = $response->json('data.locations');

    expect($locations)->toHaveCount(2)
        ->and($locations[0]['type'])->toBe('country')
        ->and($locations[1]['city']['name'])->toBe('Lahore')
        ->and($locations[1]['country_id'])->toBe($country->id);
});

it('requires the field a location type depends on', function () {
    $this->postJson('/api/v1/shipping-zones', [
        'name' => 'Broken Zone',
        'locations' => [
            ['type' => 'city'],
            ['type' => 'postal_prefix'],
        ],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors([
            'locations.0.city_id',
            'locations.1.country_id',
            'locations.1.postal_code_prefix',
        ]);
});

it('keeps zone codes unique within a tenant', function () {
    ShippingZone::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Existing',
        'code' => 'lahore',
    ]);

    $this->postJson('/api/v1/shipping-zones', [
        'name' => 'Duplicate',
        'code' => 'lahore',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['code']);
});

it('creates a tiered rate with its weight brackets', function () {
    $zone = ShippingZone::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Lahore',
        'code' => 'lahore',
    ]);

    $response = $this->postJson('/api/v1/shipping-rates', [
        'name' => 'Standard',
        'code' => 'standard',
        'shipping_zone_id' => $zone->id,
        'calculation' => 'tiered',
        'currency' => 'pkr',
        'tiers' => [
            ['min_weight_kg' => 0, 'max_weight_kg' => 1, 'amount' => 200],
            ['min_weight_kg' => 1, 'amount' => 350],
        ],
    ])
        ->assertCreated()
        ->assertJsonPath('message', 'Shipping rate created successfully');

    $data = $response->json('data');

    expect($data['currency'])->toBe('PKR')
        ->and($data['carrier'])->toBe('manual')
        ->and($data['zone']['uuid'])->toBe($zone->uuid)
        ->and($data['tiers'])->toHaveCount(2)
        ->and((float) $data['tiers'][1]['amount'])->toBe(350.0);
});

it('rejects overlapping weight brackets', function () {
    $this->postJson('/api/v1/shipping-rates', [
        'name' => 'Overlap',
        'calculation' => 'tiered',
        'currency' => 'PKR',
        'tiers' => [
            ['min_weight_kg' => 0, 'max_weight_kg' => 2, 'amount' => 200],
            ['min_weight_kg' => 1, 'max_weight_kg' => 3, 'amount' => 300],
        ],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['tiers.1.min_weight_kg']);
});

it('requires brackets for a tiered rate', function () {
    $this->postJson('/api/v1/shipping-rates', [
        'name' => 'Tiered Without Tiers',
        'calculation' => 'tiered',
        'currency' => 'PKR',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['tiers']);
});

it('rejects unknown calculations', function () {
    $this->postJson('/api/v1/shipping-rates', [
        'name' => 'Teleport',
        'calculation' => 'teleportation',
        'currency' => 'PKR',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['calculation']);
});

it('lists zones for the tenant as a collection', function () {
    ShippingZone::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Lahore Metro',
        'code' => 'lahore-metro',
    ]);

    $response = $this->getJson('/api/v1/shipping-zones')->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.name'))->toBe('Lahore Metro')
        ->and($response->json('data.0.locations'))->toBe([]);
});

it('lets merchants manage their own templates only', function () {
    $global = PackagingTemplate::create([
        'tenant_id' => null,
        'name' => 'Platform Box',
        'code' => 'platform-box-'.Str::lower(Str::random(4)),
    ]);

    $this->putJson('/api/v1/packaging-templates/'.$global->uuid, ['name' => 'Hijacked'])
        ->assertForbidden();

    $otherTenant = Tenant::create([
        'name' => 'Other Tenant',
        'slug' => 'other-tenant',
        'is_active' => true,
    ]);

    $foreign = PackagingTemplate::create([
        'tenant_id' => $otherTenant->id,
        'name' => 'Other Box',
        'code' => 'other-box',
    ]);

    $this->putJson('/api/v1/packaging-templates/'.$foreign->uuid, ['name' => 'Hijacked'])
        ->assertForbidden();

    $own = PackagingTemplate::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Our Box',
        'code' => 'our-box',
    ]);

    $this->putJson('/api/v1/packaging-templates/'.$own->uuid, ['name' => 'Our Box v2'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Our Box v2');
});

it('lets an admin create a platform-wide template', function () {
    $admin = User::factory()->create();
    $admin->assignRole('super-admin');

    $this->actingAs($admin, 'api')
        ->postJson('/api/v1/packaging-templates', [
            'name' => 'Platform Pallet',
            'code' => 'platform-pallet',
            'tenant_id' => null,
        ])
        ->assertCreated()
        ->assertJsonPath('data.tenant_id', null);
});
