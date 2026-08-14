<?php

use App\Http\Middleware\ApiAuthenticate;
use App\Models\Definition;
use App\Models\Measurement;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
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

    $this->actingAs($this->user, 'api');
});

it('creates and shows a definition', function () {
    $storeResponse = $this->postJson('/api/v1/definitions', [
        'tenant_id' => $this->tenant->id,
        'name' => 'Color',
        'kind' => 'attribute',
        'value_type' => 'text',
        'is_required' => true,
        'is_filterable' => true,
    ]);

    $storeResponse
        ->assertStatus(201)
        ->assertJson([
            'success' => true,
            'message' => 'Definition created successfully',
            'data' => [
                'tenant_id' => $this->tenant->id,
                'name' => 'Color',
                'code' => 'color',
                'kind' => 'attribute',
                'value_type' => 'text',
                'is_required' => true,
                'applicable_types' => ['product'],
            ],
        ])
        ->assertJsonMissingPath('data.is_variant');

    $uuid = $storeResponse->json('data.uuid');

    $this->getJson('/api/v1/definitions/' . $uuid)
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Definition retrieved successfully',
            'data' => [
                'uuid' => $uuid,
                'name' => 'Color',
                'code' => 'color',
                'applicable_types' => ['product'],
            ],
        ])
        ->assertJsonMissingPath('data.is_variant');
});

it('lists tenant definitions and updates one', function () {
    $definition = Definition::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Size',
        'code' => 'size',
        'kind' => 'attribute',
        'value_type' => 'text',
        'is_active' => true,
    ]);

    Definition::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Material',
        'code' => 'material',
        'kind' => 'attribute',
        'value_type' => 'text',
        'is_active' => true,
    ]);

    $this->getJson('/api/v1/definitions?tenant_id=' . $this->tenant->id)
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Definitions retrieved successfully',
        ])
        ->assertJsonCount(2, 'data');

    $this->putJson('/api/v1/definitions/' . $definition->uuid, [
        'name' => 'Display Size',
        'is_searchable' => true,
    ])
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Definition updated successfully',
            'data' => [
                'uuid' => $definition->uuid,
                'name' => 'Display Size',
                'code' => 'display_size',
                'is_searchable' => true,
            ],
        ]);
});

it('deletes a definition', function () {
    $definition = Definition::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Weight',
        'code' => 'weight',
        'kind' => 'attribute',
        'value_type' => 'number',
        'is_active' => true,
    ]);

    $this->deleteJson('/api/v1/definitions/' . $definition->uuid)
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Definition deleted successfully',
            'data' => null,
        ]);

    $this->assertSoftDeleted('definitions', ['id' => $definition->id]);
});

it('rejects a parent definition from another tenant', function () {
    $otherTenant = Tenant::create([
        'name' => 'Other Tenant',
        'slug' => 'other-tenant',
        'is_active' => true,
    ]);

    $foreignParent = Definition::create([
        'tenant_id' => $otherTenant->id,
        'name' => 'Foreign Parent',
        'code' => 'foreign_parent',
        'kind' => 'attribute',
        'value_type' => 'text',
        'is_active' => true,
    ]);

    $this->postJson('/api/v1/definitions', [
        'tenant_id' => $this->tenant->id,
        'parent_id' => $foreignParent->id,
        'name' => 'Child Definition',
        'value_type' => 'text',
    ])
        ->assertStatus(422)
        ->assertJson([
            'success' => false,
            'status_code' => 422,
        ]);

    expect(Definition::query()->where('tenant_id', $this->tenant->id)->count())->toBe(0);
});

it('rejects listing definitions for a user without an active membership', function () {
    $intruder = User::factory()->create();

    $this->actingAs($intruder, 'api');

    $this->getJson('/api/v1/definitions?tenant_id=' . $this->tenant->id)
        ->assertStatus(403)
        ->assertJsonPath('success', false);
});

it('rejects showing a definition from another tenant membership', function () {
    $definition = Definition::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Color',
        'code' => 'color',
        'kind' => 'attribute',
        'value_type' => 'text',
        'is_active' => true,
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

    $this->getJson('/api/v1/definitions/' . $definition->uuid)
        ->assertStatus(403)
        ->assertJsonPath('success', false);
});

it('creates a definition with explicit applicable types', function () {
    $response = $this->postJson('/api/v1/definitions', [
        'tenant_id' => $this->tenant->id,
        'name' => 'Finish',
        'kind' => 'attribute',
        'value_type' => 'text',
        'applicable_types' => ['variant'],
    ]);

    $uuid = $response->json('data.uuid');

    $response
        ->assertStatus(201)
        ->assertJsonPath('data.applicable_types.0', 'variant')
        ->assertJsonMissingPath('data.is_variant');

    $this->getJson('/api/v1/definitions/' . $uuid)
        ->assertOk()
        ->assertJsonPath('data.applicable_types.0', 'variant')
        ->assertJsonMissingPath('data.is_variant');
});

it('creates a definition with multi-target applicable types', function () {
    $response = $this->postJson('/api/v1/definitions', [
        'tenant_id' => $this->tenant->id,
        'name' => 'Shared Finish',
        'kind' => 'attribute',
        'value_type' => 'text',
        'applicable_types' => ['product', 'variant'],
    ]);

    $uuid = $response->json('data.uuid');

    $response
        ->assertStatus(201)
        ->assertJsonPath('data.applicable_types.0', 'product')
        ->assertJsonPath('data.applicable_types.1', 'variant')
        ->assertJsonMissingPath('data.is_variant');

    $this->getJson('/api/v1/definitions/' . $uuid)
        ->assertOk()
        ->assertJsonPath('data.applicable_types.0', 'product')
        ->assertJsonPath('data.applicable_types.1', 'variant')
        ->assertJsonMissingPath('data.is_variant');
});

it('creates grouped definitions and filters by group and section', function () {
    $storeResponse = $this->postJson('/api/v1/definitions', [
        'tenant_id' => $this->tenant->id,
        'name' => 'Voltage',
        'kind' => 'specification',
        'value_type' => 'number',
        'group_name' => 'technical',
        'section_name' => 'electrical',
    ]);

    $uuid = $storeResponse->json('data.uuid');

    Definition::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Weight',
        'code' => 'weight',
        'kind' => 'specification',
        'value_type' => 'number',
        'group_name' => 'technical',
        'section_name' => 'mechanical',
        'is_active' => true,
    ]);

    $storeResponse
        ->assertStatus(201)
        ->assertJsonPath('data.group_name', 'technical')
        ->assertJsonPath('data.section_name', 'electrical');

    $this->getJson('/api/v1/definitions/' . $uuid)
        ->assertOk()
        ->assertJsonPath('data.group_name', 'technical')
        ->assertJsonPath('data.section_name', 'electrical');

    $this->getJson('/api/v1/definitions?tenant_id=' . $this->tenant->id . '&group_name=technical&section_name=electrical')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.code', 'voltage');
});

it('exposes dynamic labels and measurements for definitions', function () {
    $measurement = Measurement::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Length',
        'code' => 'length',
        'metadata' => ['sort_order' => 10],
        'is_active' => true,
    ]);

    $unit = Unit::create([
        'tenant_id' => $this->tenant->id,
        'measurement_id' => $measurement->id,
        'name' => 'Millimeter',
        'code' => 'mm',
        'symbol' => 'mm',
        'factor' => 1,
        'precision' => 2,
        'is_active' => true,
    ]);

    $response = $this->postJson('/api/v1/definitions', [
        'tenant_id' => $this->tenant->id,
        'measurement_id' => $measurement->id,
        'unit_id' => $unit->id,
        'name' => 'Overall Length',
        'kind' => 'specification',
        'value_type' => 'number',
        'config' => [
            'frontend' => [
                'label' => 'Length',
            ],
        ],
    ]);

    $uuid = $response->json('data.uuid');

    $response
        ->assertStatus(201)
        ->assertJsonPath('data.display_label', 'Length')
        ->assertJsonPath('data.measurement_type', 'length')
        ->assertJsonPath('data.measurement.code', 'length');

    $this->getJson('/api/v1/definitions/' . $uuid)
        ->assertOk()
        ->assertJsonPath('data.display_label', 'Length')
        ->assertJsonPath('data.measurement_type', 'length')
        ->assertJsonPath('data.measurement.name', 'Length')
        ->assertJsonPath('data.unit.code', 'mm');
});

it('rejects deleting a definition from another tenant membership', function () {
    $definition = Definition::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Protected Definition',
        'code' => 'protected_definition',
        'kind' => 'attribute',
        'value_type' => 'text',
        'is_active' => true,
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

    $this->deleteJson('/api/v1/definitions/' . $definition->uuid)
        ->assertStatus(403)
        ->assertJsonPath('success', false);

    $this->assertDatabaseHas('definitions', [
        'id' => $definition->id,
        'deleted_at' => null,
    ]);
});
