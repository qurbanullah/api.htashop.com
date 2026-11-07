<?php

namespace Tests\Feature\Product;

use App\Http\Middleware\ApiAuthenticate;
use App\Models\Definition;
use App\Models\Measurement;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductShipping;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\MeasurementSeeder;
use Database\Seeders\PackagingDefinitionSeeder;
use Illuminate\Support\Str;

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

    $this->actingAs($this->user, 'api');

    $this->product = Product::create([
        'tenant_id' => $this->tenant->id,
        'organization_id' => $this->organization->id,
        'name' => 'Impact Drill',
        'slug' => 'impact-drill-'.Str::lower(Str::random(6)),
        'status' => 'active',
        'is_active' => true,
        'metadata' => ['price' => 100, 'currency' => 'USD'],
    ]);
});

function shippingMeasurementId(string $code): int
{
    return (int) Measurement::query()->where('code', $code)->value('id');
}

function shippingUnitId(string $measurement, string $code): int
{
    return (int) Unit::query()
        ->where('measurement_id', shippingMeasurementId($measurement))
        ->where('code', $code)
        ->value('id');
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function shippingPayload(array $overrides = []): array
{
    return array_merge([
        'net_weight' => 4.5,
        'gross_weight' => 5,
        'weight_unit_id' => shippingUnitId('mass', 'lb'),
        'length' => 10,
        'width' => 8,
        'height' => 6,
        'dimension_unit_id' => shippingUnitId('length', 'in'),
        'packaging_type_unit_id' => shippingUnitId('packaging', 'box'),
        'units_per_case' => 12,
        'ships_alone' => true,
        'is_fragile' => true,
        'is_stackable' => false,
        'handling_notes' => 'This way up. Do not drop.',
        'contents' => [
            ['name' => 'Impact drill', 'qty' => 1],
            ['name' => 'Carry case', 'qty' => 1],
        ],
    ], $overrides);
}

it('creates shipping data and normalises weights and dimensions', function () {
    $response = $this->putJson('/api/v1/products/'.$this->product->uuid.'/shipping', shippingPayload())
        ->assertOk()
        ->assertJsonPath('message', 'Product shipping saved successfully');

    $data = $response->json('data');

    expect($data['net_weight'])->toEqual(4.5)
        ->and($data['gross_weight'])->toEqual(5.0)
        ->and($data['weight_unit']['code'])->toBe('lb')
        // 5 lb * 0.45359237 = 2.26796185 kg
        ->and($data['gross_weight_kg'])->toBe(2.268)
        // 10 in, 8 in and 6 in in centimetres
        ->and($data['length_cm'])->toBe(25.4)
        ->and($data['width_cm'])->toBe(20.32)
        ->and($data['height_cm'])->toBe(15.24)
        ->and($data['packaging_type']['code'])->toBe('box')
        ->and($data['units_per_case'])->toBe(12)
        ->and($data['ships_alone'])->toBeTrue()
        ->and($data['is_fragile'])->toBeTrue()
        ->and($data['is_stackable'])->toBeFalse()
        ->and($data['contents'])->toHaveCount(2)
        ->and($data['is_rateable'])->toBeTrue()
        // (25.4 * 20.32 * 15.24) / 5000
        ->and($data['volumetric_weight_kg'])->toBe(1.573);
});

it('returns the saved shipping record', function () {
    $this->putJson('/api/v1/products/'.$this->product->uuid.'/shipping', shippingPayload())->assertOk();

    $this->getJson('/api/v1/products/'.$this->product->uuid.'/shipping')
        ->assertOk()
        ->assertJsonPath('message', 'Product shipping retrieved successfully')
        ->assertJsonPath('data.gross_weight_kg', 2.268)
        ->assertJsonPath('data.dimension_unit.code', 'in');
});

it('returns null when a product has no shipping record', function () {
    $this->getJson('/api/v1/products/'.$this->product->uuid.'/shipping')
        ->assertOk()
        ->assertJsonPath('data', null);
});

it('recalculates the normalised columns when the unit changes', function () {
    $this->putJson('/api/v1/products/'.$this->product->uuid.'/shipping', shippingPayload([
        'net_weight' => 1,
        'gross_weight' => 2,
        'weight_unit_id' => shippingUnitId('mass', 'kg'),
    ]))->assertOk()->assertJsonPath('data.gross_weight_kg', 2);

    $this->putJson('/api/v1/products/'.$this->product->uuid.'/shipping', shippingPayload([
        'net_weight' => 1,
        'gross_weight' => 10,
        'weight_unit_id' => shippingUnitId('mass', 'lb'),
    ]))->assertOk()->assertJsonPath('data.gross_weight_kg', 4.536);

    expect(ProductShipping::query()->where('product_id', $this->product->id)->count())->toBe(1);
});

it('rejects a gross weight below the net weight', function () {
    $this->putJson('/api/v1/products/'.$this->product->uuid.'/shipping', shippingPayload([
        'net_weight' => 5,
        'gross_weight' => 4,
    ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['gross_weight']);
});

it('rejects units that belong to the wrong measurement', function () {
    $this->putJson('/api/v1/products/'.$this->product->uuid.'/shipping', shippingPayload([
        'weight_unit_id' => shippingUnitId('length', 'cm'),
        'dimension_unit_id' => shippingUnitId('mass', 'kg'),
    ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['weight_unit_id', 'dimension_unit_id']);
});

it('rejects a packaging type that is not a packaging unit', function () {
    $this->putJson('/api/v1/products/'.$this->product->uuid.'/shipping', shippingPayload([
        'packaging_type_unit_id' => shippingUnitId('mass', 'kg'),
    ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['packaging_type_unit_id']);
});

it('accepts hazard metadata from the packaging definitions', function () {
    $this->seed(PackagingDefinitionSeeder::class);

    $definition = Definition::query()
        ->where('code', 'pkg_hazard_class')
        ->where('kind', 'packaging')
        ->firstOrFail();

    $option = $definition->options()->where('code', '3')->firstOrFail();

    $otherDefinition = Definition::query()
        ->where('code', 'pkg_packing_group')
        ->where('kind', 'packaging')
        ->firstOrFail();

    $this->putJson('/api/v1/products/'.$this->product->uuid.'/shipping', shippingPayload([
        'is_hazmat' => true,
        'un_number' => 'UN1263',
        'hazard_class_option_id' => $option->id,
        'packing_group_option_id' => $otherDefinition->options()->where('code', 'II')->value('id'),
    ]))
        ->assertOk()
        ->assertJsonPath('data.is_hazmat', true)
        ->assertJsonPath('data.un_number', 'UN1263')
        ->assertJsonPath('data.hazard_class.code', '3')
        ->assertJsonPath('data.packing_group.code', 'II');
});

it('rejects hazard options from an unrelated definition', function () {
    $this->seed(PackagingDefinitionSeeder::class);

    $definition = Definition::query()
        ->where('code', 'pkg_packing_group')
        ->where('kind', 'packaging')
        ->firstOrFail();

    $this->putJson('/api/v1/products/'.$this->product->uuid.'/shipping', shippingPayload([
        'hazard_class_option_id' => $definition->options()->value('id'),
    ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['hazard_class_option_id']);
});

it('forbids users outside the product organization', function () {
    $outsider = User::factory()->create();

    $this->actingAs($outsider, 'api')
        ->putJson('/api/v1/products/'.$this->product->uuid.'/shipping', shippingPayload())
        ->assertForbidden();

    $this->actingAs($outsider, 'api')
        ->getJson('/api/v1/products/'.$this->product->uuid.'/shipping')
        ->assertForbidden();
});

it('exposes a public packaging summary on the catalog endpoint', function () {
    $this->putJson('/api/v1/products/'.$this->product->uuid.'/shipping', shippingPayload([
        'is_hazmat' => true,
        'un_number' => 'UN1263',
    ]))->assertOk();

    $response = $this->getJson('/api/v1/catalog/products/'.$this->product->uuid)->assertOk();

    $packaging = $response->json('data.packaging');

    expect($packaging)->not->toBeNull()
        ->and($packaging['gross_weight'])->toEqual(5.0)
        ->and($packaging['weight_unit'])->toBe('lb')
        ->and($packaging['gross_weight_kg'])->toBe(2.268)
        ->and($packaging['packaging_type']['code'])->toBe('box')
        ->and($packaging['units_per_case'])->toBe(12)
        ->and($packaging['contents'])->toHaveCount(2)
        ->and($packaging['is_rateable'])->toBeTrue();

    // Internal compliance identifiers never reach the storefront payload.
    expect($packaging)->not->toHaveKeys([
        'un_number',
        'hazard_class_option_id',
        'packing_group_option_id',
    ]);
});

it('omits the packaging summary for products without shipping data', function () {
    $response = $this->getJson('/api/v1/catalog/products/'.$this->product->uuid)->assertOk();

    expect($response->json('data.packaging'))->toBeNull();
});
