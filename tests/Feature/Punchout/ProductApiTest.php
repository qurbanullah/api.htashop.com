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

it('creates and shows a product', function () {
    $storeResponse = $this->postJson('/api/v1/products', [
        'tenant_id' => $this->tenant->id,
        'organization_id' => $this->organization->id,
        'name' => 'Punchout Drill',
        'status' => 'draft',
        'summary' => 'Primary drill listing',
        'description' => 'Product description',
        'is_active' => true,
        'metadata' => ['source' => 'erp'],
    ]);

    $storeResponse
        ->assertStatus(201)
        ->assertJson([
            'success' => true,
            'message' => 'Product created successfully',
            'data' => [
                'tenant_id' => $this->tenant->id,
                'organization_id' => $this->organization->id,
                'name' => 'Punchout Drill',
                'slug' => 'punchout-drill',
                'status' => 'draft',
                'summary' => 'Primary drill listing',
                'description' => 'Product description',
                'is_active' => true,
                'metadata' => ['source' => 'erp'],
                'variants' => [],
            ],
        ]);

    $uuid = $storeResponse->json('data.uuid');

    $this->getJson('/api/v1/products/' . $uuid)
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Product retrieved successfully',
            'data' => [
                'uuid' => $uuid,
                'slug' => 'punchout-drill',
            ],
        ]);
});

it('lists tenant products and updates one', function () {
    $product = Product::create([
        'tenant_id' => $this->tenant->id,
        'organization_id' => $this->organization->id,
        'name' => 'Punchout Drill',
        'slug' => 'punchout-drill',
        'status' => 'draft',
        'is_active' => true,
    ]);

    Product::create([
        'tenant_id' => $this->tenant->id,
        'organization_id' => $this->organization->id,
        'name' => 'Punchout Saw',
        'slug' => 'punchout-saw',
        'status' => 'active',
        'is_active' => true,
    ]);

    $this->getJson('/api/v1/products?tenant_id=' . $this->tenant->id)
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Products retrieved successfully',
        ])
        ->assertJsonCount(2, 'data');

    $this->putJson('/api/v1/products/' . $product->uuid, [
        'name' => 'Punchout Drill Pro',
        'status' => 'active',
        'metadata' => ['source' => 'catalog'],
    ])
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Product updated successfully',
            'data' => [
                'uuid' => $product->uuid,
                'name' => 'Punchout Drill Pro',
                'slug' => 'punchout-drill-pro',
                'status' => 'active',
                'metadata' => ['source' => 'catalog'],
            ],
        ]);
});

it('soft deletes a product', function () {
    $product = Product::create([
        'tenant_id' => $this->tenant->id,
        'organization_id' => $this->organization->id,
        'name' => 'Punchout Drill',
        'slug' => 'punchout-drill',
        'status' => 'draft',
        'is_active' => true,
    ]);

    $this->deleteJson('/api/v1/products/' . $product->uuid)
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Product deleted successfully',
            'data' => null,
        ]);

    $this->assertSoftDeleted('products', ['id' => $product->id]);
});

it('scopes product listings to the authenticated membership organization', function () {
    Product::create([
        'tenant_id' => $this->tenant->id,
        'organization_id' => $this->organization->id,
        'name' => 'Allowed Drill',
        'slug' => 'allowed-drill',
        'status' => 'active',
        'is_active' => true,
    ]);

    $otherOrganization = Organization::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Sibling Vendor',
        'slug' => 'sibling-vendor',
        'type' => 'vendor',
        'is_active' => true,
    ]);

    Product::create([
        'tenant_id' => $this->tenant->id,
        'organization_id' => $otherOrganization->id,
        'name' => 'Blocked Drill',
        'slug' => 'blocked-drill',
        'status' => 'active',
        'is_active' => true,
    ]);

    $response = $this->getJson('/api/v1/products?tenant_id=' . $this->tenant->id);

    $response
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Allowed Drill');
});

it('rejects an organization from another tenant', function () {
    $otherTenant = Tenant::create([
        'name' => 'Other Tenant',
        'slug' => 'other-tenant',
        'is_active' => true,
    ]);

    $otherOrganization = Organization::create([
        'tenant_id' => $otherTenant->id,
        'name' => 'Other Vendor',
        'slug' => 'other-vendor',
        'type' => 'vendor',
        'is_active' => true,
    ]);

    $this->postJson('/api/v1/products', [
        'tenant_id' => $this->tenant->id,
        'organization_id' => $otherOrganization->id,
        'name' => 'Invalid Product',
    ])
        ->assertStatus(422)
        ->assertJson([
            'success' => false,
            'status_code' => 422,
        ]);

    expect(Product::count())->toBe(0);
});

it('rejects updating a product to an organization from another tenant', function () {
    $product = Product::create([
        'tenant_id' => $this->tenant->id,
        'organization_id' => $this->organization->id,
        'name' => 'Punchout Drill',
        'slug' => 'punchout-drill',
        'status' => 'draft',
        'is_active' => true,
    ]);

    $otherTenant = Tenant::create([
        'name' => 'Other Tenant',
        'slug' => 'other-tenant',
        'is_active' => true,
    ]);

    $otherOrganization = Organization::create([
        'tenant_id' => $otherTenant->id,
        'name' => 'Other Vendor',
        'slug' => 'other-vendor',
        'type' => 'vendor',
        'is_active' => true,
    ]);

    $this->putJson('/api/v1/products/' . $product->uuid, [
        'organization_id' => $otherOrganization->id,
    ])
        ->assertStatus(422)
        ->assertJson([
            'success' => false,
            'status_code' => 422,
        ]);

    expect($product->fresh()->organization_id)->toBe($this->organization->id);
});

it('rejects listing products for a user without an active membership', function () {
    $intruder = User::factory()->create();

    $this->actingAs($intruder, 'api');

    $this->getJson('/api/v1/products?tenant_id=' . $this->tenant->id)
        ->assertStatus(403)
        ->assertJsonPath('success', false);
});

it('rejects showing a product from another organization membership', function () {
    $product = Product::create([
        'tenant_id' => $this->tenant->id,
        'organization_id' => $this->organization->id,
        'name' => 'Punchout Drill',
        'slug' => 'punchout-drill',
        'status' => 'active',
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

    $this->getJson('/api/v1/products/' . $product->uuid)
        ->assertStatus(403)
        ->assertJsonPath('success', false);
});

it('rejects deleting a product from another organization membership', function () {
    $product = Product::create([
        'tenant_id' => $this->tenant->id,
        'organization_id' => $this->organization->id,
        'name' => 'Protected Drill',
        'slug' => 'protected-drill',
        'status' => 'active',
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

    $this->deleteJson('/api/v1/products/' . $product->uuid)
        ->assertStatus(403)
        ->assertJsonPath('success', false);

    $this->assertDatabaseHas('products', [
        'id' => $product->id,
        'deleted_at' => null,
    ]);
});

it('shows product attributes and specifications', function () {
    $product = Product::create([
        'tenant_id' => $this->tenant->id,
        'organization_id' => $this->organization->id,
        'name' => 'Punchout Drill',
        'slug' => 'punchout-drill',
        'status' => 'active',
        'is_active' => true,
    ]);

    $attributeDefinition = Definition::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Color',
        'code' => 'color',
        'kind' => 'attribute',
        'value_type' => 'text',
        'group_name' => 'presentation',
        'section_name' => 'finish',
        'is_active' => true,
    ]);

    $attributeDefinition->targets()->create([
        'target_type' => 'product',
    ]);

    $measurement = Measurement::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Voltage',
        'code' => 'voltage',
        'metadata' => ['sort_order' => 10],
        'is_active' => true,
    ]);

    $unit = Unit::create([
        'tenant_id' => $this->tenant->id,
        'measurement_id' => $measurement->id,
        'name' => 'Volt',
        'code' => 'v',
        'symbol' => 'V',
        'factor' => 1,
        'precision' => 2,
        'is_active' => true,
    ]);

    $specificationDefinition = Definition::create([
        'tenant_id' => $this->tenant->id,
        'measurement_id' => $measurement->id,
        'unit_id' => $unit->id,
        'name' => 'Voltage',
        'code' => 'voltage',
        'kind' => 'specification',
        'value_type' => 'number',
        'group_name' => 'technical',
        'section_name' => 'electrical',
        'config' => ['frontend' => ['label' => 'Power Voltage']],
        'is_active' => true,
    ]);

    $specificationDefinition->targets()->create([
        'target_type' => 'product',
    ]);

    $product->values()->create([
        'tenant_id' => $this->tenant->id,
        'definition_id' => $attributeDefinition->id,
        'value_text' => 'Blue',
        'channel' => 'web',
    ]);

    $product->values()->create([
        'tenant_id' => $this->tenant->id,
        'definition_id' => $specificationDefinition->id,
        'unit_id' => $unit->id,
        'value_number' => 220,
        'normalized_number' => 220,
        'channel' => 'web',
    ]);

    $this->getJson('/api/v1/products/' . $product->uuid)
        ->assertOk()
        ->assertJsonPath('data.attributes.0.definition.code', 'color')
        ->assertJsonPath('data.attributes.0.definition.group_name', 'presentation')
        ->assertJsonPath('data.attributes.0.value_text', 'Blue')
        ->assertJsonPath('data.attribute_groups.0.group_name', 'presentation')
        ->assertJsonPath('data.attribute_groups.0.sections.0.section_name', 'finish')
        ->assertJsonPath('data.attribute_groups.0.sections.0.items.0.definition.code', 'color')
        ->assertJsonPath('data.specifications.0.definition.code', 'voltage')
        ->assertJsonPath('data.specifications.0.definition.display_label', 'Power Voltage')
        ->assertJsonPath('data.specifications.0.definition.group_name', 'technical')
        ->assertJsonPath('data.specifications.0.definition.measurement_type', 'voltage')
        ->assertJsonPath('data.specifications.0.definition.measurement.code', 'voltage')
        ->assertJsonPath('data.specification_groups.0.group_name', 'technical')
        ->assertJsonPath('data.specification_groups.0.sections.0.section_name', 'electrical')
        ->assertJsonPath('data.specification_groups.0.sections.0.items.0.definition.code', 'voltage')
        ->assertJsonPath('data.specifications.0.unit.measurement.code', 'voltage')
        ->assertJsonPath('data.specifications.0.value_number', '220.000000');

    $this->getJson('/api/v1/products?tenant_id=' . $this->tenant->id . '&search=Punchout Drill')
        ->assertOk()
        ->assertJsonPath('data.0.attributes.0.definition.code', 'color')
        ->assertJsonPath('data.0.attribute_groups.0.group_name', 'presentation')
        ->assertJsonPath('data.0.specifications.0.definition.code', 'voltage')
        ->assertJsonPath('data.0.specifications.0.definition.measurement_type', 'voltage');
});

it('exposes product primary image and labeled gallery assets', function () {
    $product = Product::create([
        'tenant_id' => $this->tenant->id,
        'organization_id' => $this->organization->id,
        'name' => 'Punchout Drill Media',
        'slug' => 'punchout-drill-media',
        'status' => 'active',
        'is_active' => true,
    ]);

    $product->attachDam([
        'uuid' => (string) \Illuminate\Support\Str::uuid(),
        'file_name' => 'front-view.png',
        'disk' => config('dam.default_disk'),
        'bucket' => config('dam.default_bucket'),
        'object_key' => 'images/products/front-view.png',
        'is_current' => true,
        'sort_order' => 1,
        'collection_keys' => ['primary', 'front_view'],
    ], 'image');

    $product->attachDam([
        'uuid' => (string) \Illuminate\Support\Str::uuid(),
        'file_name' => 'top-view.png',
        'disk' => config('dam.default_disk'),
        'bucket' => config('dam.default_bucket'),
        'object_key' => 'images/products/top-view.png',
        'is_current' => true,
        'sort_order' => 2,
        'collection_keys' => ['top_view'],
    ], 'image');

    $this->getJson('/api/v1/products/' . $product->uuid)
        ->assertOk()
        ->assertJsonPath('data.primary_image.object_key', 'images/products/front-view.png')
        ->assertJsonPath('data.media.image.0.object_key', 'images/products/front-view.png')
        ->assertJsonPath('data.media.image.1.object_key', 'images/products/top-view.png')
        ->assertJsonPath('data.media.image.1.collections.1.key', 'top_view');
});

it('filters products by definition group, section, and kind', function () {
    $matchingProduct = Product::create([
        'tenant_id' => $this->tenant->id,
        'organization_id' => $this->organization->id,
        'name' => 'Punchout Drill',
        'slug' => 'punchout-drill-filter',
        'status' => 'active',
        'is_active' => true,
    ]);

    $nonMatchingProduct = Product::create([
        'tenant_id' => $this->tenant->id,
        'organization_id' => $this->organization->id,
        'name' => 'Punchout Saw',
        'slug' => 'punchout-saw-filter',
        'status' => 'active',
        'is_active' => true,
    ]);

    $electricalDefinition = Definition::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Voltage',
        'code' => 'voltage_filter',
        'kind' => 'specification',
        'value_type' => 'number',
        'group_name' => 'technical',
        'section_name' => 'electrical',
        'is_active' => true,
    ]);

    $electricalDefinition->targets()->create([
        'target_type' => 'product',
    ]);

    $attributeDefinition = Definition::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Power Label',
        'code' => 'power_label_filter',
        'kind' => 'attribute',
        'value_type' => 'text',
        'group_name' => 'technical',
        'section_name' => 'electrical',
        'is_active' => true,
    ]);

    $attributeDefinition->targets()->create([
        'target_type' => 'product',
    ]);

    $mechanicalDefinition = Definition::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Weight',
        'code' => 'weight_filter',
        'kind' => 'specification',
        'value_type' => 'number',
        'group_name' => 'technical',
        'section_name' => 'mechanical',
        'is_active' => true,
    ]);

    $mechanicalDefinition->targets()->create([
        'target_type' => 'product',
    ]);

    $matchingProduct->values()->create([
        'tenant_id' => $this->tenant->id,
        'definition_id' => $electricalDefinition->id,
        'value_number' => 220,
        'normalized_number' => 220,
        'channel' => 'web',
    ]);

    $nonMatchingProduct->values()->create([
        'tenant_id' => $this->tenant->id,
        'definition_id' => $mechanicalDefinition->id,
        'value_number' => 12,
        'normalized_number' => 12,
        'channel' => 'web',
    ]);

    $nonMatchingProduct->values()->create([
        'tenant_id' => $this->tenant->id,
        'definition_id' => $attributeDefinition->id,
        'value_text' => '220V Class',
        'channel' => 'web',
    ]);

    $this->getJson('/api/v1/products?tenant_id=' . $this->tenant->id . '&definition_group_name=technical&definition_section_name=electrical&definition_kind=specification')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.uuid', $matchingProduct->uuid);
});
