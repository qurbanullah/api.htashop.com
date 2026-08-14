<?php

use App\Http\Middleware\ApiAuthenticate;
use App\Models\Definition;
use App\Models\Measurement;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Models\Value;
use App\Models\Variant;
use Illuminate\Foundation\Testing\RefreshDatabase;

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

    $this->product = Product::create([
        'tenant_id' => $this->tenant->id,
        'organization_id' => $this->organization->id,
        'name' => 'Punchout Drill',
        'slug' => 'punchout-drill',
        'status' => 'draft',
        'is_active' => true,
    ]);

    $this->measurement = Measurement::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Length',
        'code' => 'length',
        'is_active' => true,
    ]);

    $this->unit = Unit::create([
        'tenant_id' => $this->tenant->id,
        'measurement_id' => $this->measurement->id,
        'name' => 'Centimeter',
        'code' => 'cm',
        'symbol' => 'cm',
        'factor' => 0.01,
        'offset' => 0,
        'precision' => 2,
        'is_active' => true,
    ]);

    $this->definition = Definition::create([
        'tenant_id' => $this->tenant->id,
        'measurement_id' => $this->measurement->id,
        'unit_id' => $this->unit->id,
        'name' => 'Length',
        'code' => 'length',
        'kind' => 'attribute',
        'value_type' => 'number',
        'is_active' => true,
    ]);

    $this->definition->targets()->create([
        'target_type' => 'product',
    ]);

    $this->actingAs($this->user, 'api');
});

it('creates and shows a numeric value for a product', function () {
    $storeResponse = $this->postJson('/api/v1/values', [
        'tenant_id' => $this->tenant->id,
        'valuable_type' => 'product',
        'valuable_uuid' => $this->product->uuid,
        'definition_id' => $this->definition->id,
        'unit_id' => $this->unit->id,
        'value_number' => 10,
        'locale' => 'en',
        'channel' => 'web',
    ]);

    $storeResponse
        ->assertStatus(201)
        ->assertJson([
            'success' => true,
            'message' => 'Value created successfully',
            'data' => [
                'tenant_id' => $this->tenant->id,
                'definition_id' => $this->definition->id,
                'unit_id' => $this->unit->id,
                'value_number' => '10.000000',
                'normalized_number' => '0.100000',
                'locale' => 'en',
                'channel' => 'web',
                'definition' => [
                    'id' => $this->definition->id,
                    'uuid' => $this->definition->uuid,
                    'name' => 'Length',
                ],
                'valuable' => [
                    'type' => 'product',
                    'uuid' => $this->product->uuid,
                    'name' => 'Punchout Drill',
                ],
            ],
        ]);

    $id = $storeResponse->json('data.id');

    $this->getJson('/api/v1/values/' . $id)
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Value retrieved successfully',
            'data' => [
                'id' => $id,
                'normalized_number' => '0.100000',
            ],
        ]);
});

it('lists tenant values and updates one', function () {
    $value = $this->product->values()->create([
        'tenant_id' => $this->tenant->id,
        'definition_id' => $this->definition->id,
        'unit_id' => $this->unit->id,
        'value_number' => 10,
        'normalized_number' => 0.1,
        'locale' => 'en',
        'channel' => 'web',
    ]);

    $this->product->values()->create([
        'tenant_id' => $this->tenant->id,
        'definition_id' => $this->definition->id,
        'unit_id' => $this->unit->id,
        'value_number' => 20,
        'normalized_number' => 0.2,
        'locale' => 'en',
        'channel' => 'erp',
    ]);

    $this->getJson('/api/v1/values?tenant_id=' . $this->tenant->id . '&valuable_type=product&valuable_uuid=' . $this->product->uuid)
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Values retrieved successfully',
        ])
        ->assertJsonCount(2, 'data');

    $this->putJson('/api/v1/values/' . $value->id, [
        'value_number' => 25.5,
        'channel' => 'catalog',
    ])
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Value updated successfully',
            'data' => [
                'id' => $value->id,
                'value_number' => '25.500000',
                'normalized_number' => '0.255000',
                'channel' => 'catalog',
            ],
        ]);
});

it('restricts tenant value listings to the authenticated organization', function () {
    $otherOrganization = Organization::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Other Vendor',
        'slug' => 'other-vendor',
        'type' => 'vendor',
        'is_active' => true,
    ]);

    $otherProduct = Product::create([
        'tenant_id' => $this->tenant->id,
        'organization_id' => $otherOrganization->id,
        'name' => 'Other Drill',
        'slug' => 'other-drill',
        'status' => 'draft',
        'is_active' => true,
    ]);

    $this->product->values()->create([
        'tenant_id' => $this->tenant->id,
        'definition_id' => $this->definition->id,
        'unit_id' => $this->unit->id,
        'value_number' => 10,
        'normalized_number' => 0.1,
        'locale' => 'en',
        'channel' => 'web',
    ]);

    $otherProduct->values()->create([
        'tenant_id' => $this->tenant->id,
        'definition_id' => $this->definition->id,
        'unit_id' => $this->unit->id,
        'value_number' => 20,
        'normalized_number' => 0.2,
        'locale' => 'en',
        'channel' => 'web',
    ]);

    $this->getJson('/api/v1/values?tenant_id=' . $this->tenant->id)
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('deletes a value', function () {
    $value = $this->product->values()->create([
        'tenant_id' => $this->tenant->id,
        'definition_id' => $this->definition->id,
        'unit_id' => $this->unit->id,
        'value_number' => 10,
        'normalized_number' => 0.1,
        'locale' => 'en',
        'channel' => 'web',
    ]);

    $this->deleteJson('/api/v1/values/' . $value->id)
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Value deleted successfully',
            'data' => null,
        ]);

    $this->assertDatabaseMissing('values', ['id' => $value->id]);
});

it('rejects assigning a variant-targeted definition to a product', function () {
    $variantDefinition = Definition::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Finish Variant',
        'code' => 'finish_variant',
        'kind' => 'attribute',
        'value_type' => 'text',
        'is_active' => true,
    ]);

    $variantDefinition->targets()->create([
        'target_type' => 'variant',
    ]);

    $this->postJson('/api/v1/values', [
        'tenant_id' => $this->tenant->id,
        'valuable_type' => 'product',
        'valuable_uuid' => $this->product->uuid,
        'definition_id' => $variantDefinition->id,
        'value_text' => 'Matte',
    ])
        ->assertStatus(422)
        ->assertJson([
            'success' => false,
            'status_code' => 422,
        ]);

    expect(Value::count())->toBe(0);
});

it('rejects assigning a definition that explicitly targets variants to a product', function () {
    $variantOnlyDefinition = Definition::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Housing Variant',
        'code' => 'housing_variant',
        'kind' => 'attribute',
        'value_type' => 'text',
        'is_active' => true,
    ]);

    $variantOnlyDefinition->targets()->create([
        'target_type' => 'variant',
    ]);

    $this->postJson('/api/v1/values', [
        'tenant_id' => $this->tenant->id,
        'valuable_type' => 'product',
        'valuable_uuid' => $this->product->uuid,
        'definition_id' => $variantOnlyDefinition->id,
        'value_text' => 'Matte',
    ])
        ->assertStatus(422)
        ->assertJson([
            'success' => false,
            'status_code' => 422,
        ]);

    expect(Value::count())->toBe(0);
});

it('allows assigning a multi-target definition to both a product and a variant', function () {
    $sharedDefinition = Definition::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Shared Length',
        'code' => 'shared_length',
        'kind' => 'attribute',
        'value_type' => 'number',
        'measurement_id' => $this->measurement->id,
        'unit_id' => $this->unit->id,
        'is_active' => true,
    ]);

    $sharedDefinition->targets()->createMany([
        ['target_type' => 'product'],
        ['target_type' => 'variant'],
    ]);

    $variant = Variant::create([
        'product_id' => $this->product->id,
        'name' => 'Punchout Drill Blue',
        'slug' => 'punchout-drill-blue',
        'status' => 'active',
        'is_active' => true,
    ]);

    $this->postJson('/api/v1/values', [
        'tenant_id' => $this->tenant->id,
        'valuable_type' => 'product',
        'valuable_uuid' => $this->product->uuid,
        'definition_id' => $sharedDefinition->id,
        'unit_id' => $this->unit->id,
        'value_number' => 15,
    ])
        ->assertStatus(201)
        ->assertJsonPath('data.valuable.type', 'product')
        ->assertJsonPath('data.definition.code', 'shared_length');

    $this->postJson('/api/v1/values', [
        'tenant_id' => $this->tenant->id,
        'valuable_type' => 'variant',
        'valuable_uuid' => $variant->uuid,
        'definition_id' => $sharedDefinition->id,
        'unit_id' => $this->unit->id,
        'value_number' => 18,
    ])
        ->assertStatus(201)
        ->assertJsonPath('data.valuable.type', 'variant')
        ->assertJsonPath('data.definition.code', 'shared_length');

    expect(Value::query()->where('definition_id', $sharedDefinition->id)->count())->toBe(2);
});

it('rejects creating a value for a user without an active membership', function () {
    $intruder = User::factory()->create();

    $this->actingAs($intruder, 'api');

    $this->postJson('/api/v1/values', [
        'tenant_id' => $this->tenant->id,
        'valuable_type' => 'product',
        'valuable_uuid' => $this->product->uuid,
        'definition_id' => $this->definition->id,
        'unit_id' => $this->unit->id,
        'value_number' => 10,
    ])
        ->assertStatus(403)
        ->assertJsonPath('success', false);

    expect(Value::count())->toBe(0);
});

it('rejects updating a value from another organization membership', function () {
    $value = $this->product->values()->create([
        'tenant_id' => $this->tenant->id,
        'definition_id' => $this->definition->id,
        'unit_id' => $this->unit->id,
        'value_number' => 10,
        'normalized_number' => 0.1,
        'locale' => 'en',
        'channel' => 'web',
    ]);

    $outsider = User::factory()->create();
    $otherTenant = Tenant::create([
        'name' => 'Access Tenant',
        'slug' => 'access-tenant',
        'is_active' => true,
    ]);
    $otherOrganization = Organization::create([
        'tenant_id' => $otherTenant->id,
        'name' => 'Access Vendor',
        'slug' => 'access-vendor',
        'type' => 'vendor',
        'is_active' => true,
    ]);

    Membership::create([
        'tenant_id' => $otherTenant->id,
        'organization_id' => $otherOrganization->id,
        'user_id' => $outsider->id,
        'role' => 'owner',
        'is_primary' => true,
        'is_active' => true,
    ]);

    $this->actingAs($outsider, 'api');

    $this->putJson('/api/v1/values/' . $value->id, [
        'value_number' => 25.5,
    ])
        ->assertStatus(403)
        ->assertJsonPath('success', false);

    expect($value->fresh()->value_number)->toBe('10.000000');
});

it('rejects deleting a value from another organization membership', function () {
    $value = $this->product->values()->create([
        'tenant_id' => $this->tenant->id,
        'definition_id' => $this->definition->id,
        'unit_id' => $this->unit->id,
        'value_number' => 10,
        'normalized_number' => 0.1,
        'locale' => 'en',
        'channel' => 'web',
    ]);

    $outsider = User::factory()->create();
    $otherTenant = Tenant::create([
        'name' => 'Access Tenant',
        'slug' => 'access-tenant-delete',
        'is_active' => true,
    ]);
    $otherOrganization = Organization::create([
        'tenant_id' => $otherTenant->id,
        'name' => 'Access Vendor',
        'slug' => 'access-vendor-delete',
        'type' => 'vendor',
        'is_active' => true,
    ]);

    Membership::create([
        'tenant_id' => $otherTenant->id,
        'organization_id' => $otherOrganization->id,
        'user_id' => $outsider->id,
        'role' => 'owner',
        'is_primary' => true,
        'is_active' => true,
    ]);

    $this->actingAs($outsider, 'api');

    $this->deleteJson('/api/v1/values/' . $value->id)
        ->assertStatus(403)
        ->assertJsonPath('success', false);

    $this->assertDatabaseHas('values', [
        'id' => $value->id,
        'value_number' => '10.000000',
    ]);
});
