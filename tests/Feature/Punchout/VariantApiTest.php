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
        'status' => 'active',
        'is_active' => true,
    ]);

    $this->actingAs($this->user, 'api');
});

it('creates and shows a variant', function () {
    $storeResponse = $this->postJson('/api/v1/variants', [
        'product_id' => $this->product->id,
        'name' => 'Punchout Drill Blue',
        'status' => 'draft',
        'summary' => 'Blue finish variant',
        'description' => 'Variant description',
        'configuration' => ['color' => 'blue'],
        'is_default' => true,
        'is_active' => true,
        'metadata' => ['source' => 'erp'],
    ]);

    $storeResponse
        ->assertStatus(201)
        ->assertJson([
            'success' => true,
            'message' => 'Variant created successfully',
            'data' => [
                'product_id' => $this->product->id,
                'name' => 'Punchout Drill Blue',
                'slug' => 'punchout-drill-blue',
                'status' => 'draft',
                'summary' => 'Blue finish variant',
                'description' => 'Variant description',
                'configuration' => ['color' => 'blue'],
                'is_default' => true,
                'is_active' => true,
                'metadata' => ['source' => 'erp'],
            ],
        ]);

    $uuid = $storeResponse->json('data.uuid');

    $this->getJson('/api/v1/variants/' . $uuid)
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Variant retrieved successfully',
            'data' => [
                'uuid' => $uuid,
                'slug' => 'punchout-drill-blue',
            ],
        ]);
});

it('lists product variants and updates one', function () {
    $variant = Variant::create([
        'product_id' => $this->product->id,
        'name' => 'Punchout Drill Blue',
        'slug' => 'punchout-drill-blue',
        'status' => 'draft',
        'configuration' => ['color' => 'blue'],
        'is_default' => false,
        'is_active' => true,
    ]);

    Variant::create([
        'product_id' => $this->product->id,
        'name' => 'Punchout Drill Red',
        'slug' => 'punchout-drill-red',
        'status' => 'active',
        'configuration' => ['color' => 'red'],
        'is_default' => true,
        'is_active' => true,
    ]);

    $this->getJson('/api/v1/variants?product_id=' . $this->product->id)
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Variants retrieved successfully',
        ])
        ->assertJsonCount(2, 'data');

    $this->putJson('/api/v1/variants/' . $variant->uuid, [
        'name' => 'Punchout Drill Blue Pro',
        'configuration' => ['color' => 'blue', 'size' => 'pro'],
        'is_default' => true,
    ])
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Variant updated successfully',
            'data' => [
                'uuid' => $variant->uuid,
                'name' => 'Punchout Drill Blue Pro',
                'slug' => 'punchout-drill-blue-pro',
                'configuration' => ['color' => 'blue', 'size' => 'pro'],
                'is_default' => true,
            ],
        ]);
});

it('soft deletes a variant', function () {
    $variant = Variant::create([
        'product_id' => $this->product->id,
        'name' => 'Punchout Drill Blue',
        'slug' => 'punchout-drill-blue',
        'status' => 'draft',
        'is_active' => true,
    ]);

    $this->deleteJson('/api/v1/variants/' . $variant->uuid)
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Variant deleted successfully',
            'data' => null,
        ]);

    $this->assertSoftDeleted('variants', ['id' => $variant->id]);
});

it('scopes variant listings to the authenticated membership organization', function () {
    Variant::create([
        'product_id' => $this->product->id,
        'name' => 'Allowed Variant',
        'slug' => 'allowed-variant',
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

    $siblingProduct = Product::create([
        'tenant_id' => $this->tenant->id,
        'organization_id' => $otherOrganization->id,
        'name' => 'Sibling Drill',
        'slug' => 'sibling-drill',
        'status' => 'active',
        'is_active' => true,
    ]);

    Variant::create([
        'product_id' => $siblingProduct->id,
        'name' => 'Blocked Variant',
        'slug' => 'blocked-variant',
        'status' => 'active',
        'is_active' => true,
    ]);

    $response = $this->getJson('/api/v1/variants?tenant_id=' . $this->tenant->id);

    $response
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Allowed Variant');
});

it('rejects creating a variant for an inactive product', function () {
    $inactiveProduct = Product::create([
        'tenant_id' => $this->tenant->id,
        'organization_id' => $this->organization->id,
        'name' => 'Inactive Drill',
        'slug' => 'inactive-drill',
        'status' => 'draft',
        'is_active' => false,
    ]);

    $this->postJson('/api/v1/variants', [
        'product_id' => $inactiveProduct->id,
        'name' => 'Inactive Variant',
    ])
        ->assertStatus(422)
        ->assertJson([
            'success' => false,
            'status_code' => 422,
        ]);

    expect(Variant::count())->toBe(0);
});

it('rejects moving a variant to a product in another tenant', function () {
    $variant = Variant::create([
        'product_id' => $this->product->id,
        'name' => 'Punchout Drill Blue',
        'slug' => 'punchout-drill-blue',
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

    $foreignProduct = Product::create([
        'tenant_id' => $otherTenant->id,
        'organization_id' => $otherOrganization->id,
        'name' => 'Foreign Drill',
        'slug' => 'foreign-drill',
        'status' => 'active',
        'is_active' => true,
    ]);

    $this->putJson('/api/v1/variants/' . $variant->uuid, [
        'product_id' => $foreignProduct->id,
    ])
        ->assertStatus(422)
        ->assertJson([
            'success' => false,
            'status_code' => 422,
        ]);

    expect($variant->fresh()->product_id)->toBe($this->product->id);
});

it('rejects moving a variant to another organization in the same tenant', function () {
    $variant = Variant::create([
        'product_id' => $this->product->id,
        'name' => 'Punchout Drill Blue',
        'slug' => 'punchout-drill-blue',
        'status' => 'draft',
        'is_active' => true,
    ]);

    $otherOrganization = Organization::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Sibling Vendor',
        'slug' => 'sibling-vendor',
        'type' => 'vendor',
        'is_active' => true,
    ]);

    $siblingProduct = Product::create([
        'tenant_id' => $this->tenant->id,
        'organization_id' => $otherOrganization->id,
        'name' => 'Sibling Drill',
        'slug' => 'sibling-drill',
        'status' => 'active',
        'is_active' => true,
    ]);

    $this->putJson('/api/v1/variants/' . $variant->uuid, [
        'product_id' => $siblingProduct->id,
    ])
        ->assertStatus(422)
        ->assertJson([
            'success' => false,
            'status_code' => 422,
        ]);

    expect($variant->fresh()->product_id)->toBe($this->product->id);
});

it('rejects creating a variant for a user without an active membership', function () {
    $intruder = User::factory()->create();

    $this->actingAs($intruder, 'api');

    $this->postJson('/api/v1/variants', [
        'product_id' => $this->product->id,
        'name' => 'Unauthorized Variant',
    ])
        ->assertStatus(403)
        ->assertJsonPath('success', false);

    expect(Variant::count())->toBe(0);
});

it('rejects updating a variant from another organization membership', function () {
    $variant = Variant::create([
        'product_id' => $this->product->id,
        'name' => 'Punchout Drill Blue',
        'slug' => 'punchout-drill-blue',
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

    $this->putJson('/api/v1/variants/' . $variant->uuid, [
        'name' => 'Unauthorized Update',
    ])
        ->assertStatus(403)
        ->assertJsonPath('success', false);

    expect($variant->fresh()->name)->toBe('Punchout Drill Blue');
});

it('rejects deleting a variant from another organization membership', function () {
    $variant = Variant::create([
        'product_id' => $this->product->id,
        'name' => 'Protected Variant',
        'slug' => 'protected-variant',
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

    $this->deleteJson('/api/v1/variants/' . $variant->uuid)
        ->assertStatus(403)
        ->assertJsonPath('success', false);

    $this->assertDatabaseHas('variants', [
        'id' => $variant->id,
        'deleted_at' => null,
    ]);
});

it('shows variant attributes and specifications', function () {
    $variant = Variant::create([
        'product_id' => $this->product->id,
        'name' => 'Punchout Drill Blue',
        'slug' => 'punchout-drill-blue',
        'status' => 'active',
        'is_active' => true,
    ]);

    $attributeDefinition = Definition::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Finish',
        'code' => 'finish',
        'kind' => 'attribute',
        'value_type' => 'text',
        'group_name' => 'presentation',
        'section_name' => 'finish',
        'is_active' => true,
    ]);

    $attributeDefinition->targets()->create([
        'target_type' => 'variant',
    ]);

    $measurement = Measurement::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Torque',
        'code' => 'torque',
        'metadata' => ['category' => 'mechanical'],
        'is_active' => true,
    ]);

    $unit = Unit::create([
        'tenant_id' => $this->tenant->id,
        'measurement_id' => $measurement->id,
        'name' => 'Newton meter',
        'code' => 'nm',
        'symbol' => 'Nm',
        'factor' => 1,
        'precision' => 2,
        'is_active' => true,
    ]);

    $specificationDefinition = Definition::create([
        'tenant_id' => $this->tenant->id,
        'measurement_id' => $measurement->id,
        'unit_id' => $unit->id,
        'name' => 'Torque',
        'code' => 'torque',
        'kind' => 'specification',
        'value_type' => 'number',
        'group_name' => 'technical',
        'section_name' => 'mechanical',
        'config' => ['frontend' => ['label' => 'Max Torque']],
        'is_active' => true,
    ]);

    $specificationDefinition->targets()->create([
        'target_type' => 'variant',
    ]);

    $variant->values()->create([
        'tenant_id' => $this->tenant->id,
        'definition_id' => $attributeDefinition->id,
        'value_text' => 'Matte',
        'channel' => 'web',
    ]);

    $variant->values()->create([
        'tenant_id' => $this->tenant->id,
        'definition_id' => $specificationDefinition->id,
        'unit_id' => $unit->id,
        'value_number' => 45,
        'normalized_number' => 45,
        'channel' => 'web',
    ]);

    $this->getJson('/api/v1/variants/' . $variant->uuid)
        ->assertOk()
        ->assertJsonPath('data.attributes.0.definition.code', 'finish')
        ->assertJsonPath('data.attributes.0.definition.group_name', 'presentation')
        ->assertJsonPath('data.attributes.0.value_text', 'Matte')
        ->assertJsonPath('data.attribute_groups.0.group_name', 'presentation')
        ->assertJsonPath('data.attribute_groups.0.sections.0.section_name', 'finish')
        ->assertJsonPath('data.attribute_groups.0.sections.0.items.0.definition.code', 'finish')
        ->assertJsonPath('data.specifications.0.definition.code', 'torque')
        ->assertJsonPath('data.specifications.0.definition.display_label', 'Max Torque')
        ->assertJsonPath('data.specifications.0.definition.group_name', 'technical')
        ->assertJsonPath('data.specifications.0.definition.measurement_type', 'torque')
        ->assertJsonPath('data.specifications.0.definition.measurement.code', 'torque')
        ->assertJsonPath('data.specification_groups.0.group_name', 'technical')
        ->assertJsonPath('data.specification_groups.0.sections.0.section_name', 'mechanical')
        ->assertJsonPath('data.specification_groups.0.sections.0.items.0.definition.code', 'torque')
        ->assertJsonPath('data.specifications.0.unit.measurement.code', 'torque')
        ->assertJsonPath('data.specifications.0.value_number', '45.000000');

    $this->getJson('/api/v1/variants?product_id=' . $this->product->id . '&search=Punchout Drill Blue')
        ->assertOk()
        ->assertJsonPath('data.0.attributes.0.definition.code', 'finish')
        ->assertJsonPath('data.0.attribute_groups.0.group_name', 'presentation')
        ->assertJsonPath('data.0.specifications.0.definition.code', 'torque');
});

it('exposes variant primary image and labeled gallery assets', function () {
    $variant = Variant::create([
        'product_id' => $this->product->id,
        'name' => 'Punchout Drill Media Variant',
        'slug' => 'punchout-drill-media-variant',
        'status' => 'active',
        'is_active' => true,
    ]);

    $variant->attachDam([
        'uuid' => (string) \Illuminate\Support\Str::uuid(),
        'file_name' => 'left-view.png',
        'disk' => config('dam.default_disk'),
        'bucket' => config('dam.default_bucket'),
        'object_key' => 'images/variants/left-view.png',
        'is_current' => true,
        'sort_order' => 1,
        'collection_keys' => ['primary', 'left_view'],
    ], 'image');

    $variant->attachDam([
        'uuid' => (string) \Illuminate\Support\Str::uuid(),
        'file_name' => 'right-view.png',
        'disk' => config('dam.default_disk'),
        'bucket' => config('dam.default_bucket'),
        'object_key' => 'images/variants/right-view.png',
        'is_current' => true,
        'sort_order' => 2,
        'collection_keys' => ['right_view'],
    ], 'image');

    $this->getJson('/api/v1/variants/' . $variant->uuid)
        ->assertOk()
        ->assertJsonPath('data.primary_image.object_key', 'images/variants/left-view.png')
        ->assertJsonPath('data.media.image.0.object_key', 'images/variants/left-view.png')
        ->assertJsonPath('data.media.image.1.object_key', 'images/variants/right-view.png')
        ->assertJsonPath('data.media.image.1.collections.1.key', 'right_view');
});

it('filters variants by definition group, section, and kind', function () {
    $matchingVariant = Variant::create([
        'product_id' => $this->product->id,
        'name' => 'Punchout Drill Blue',
        'slug' => 'punchout-drill-blue-filter',
        'status' => 'active',
        'is_active' => true,
    ]);

    $nonMatchingVariant = Variant::create([
        'product_id' => $this->product->id,
        'name' => 'Punchout Drill Red',
        'slug' => 'punchout-drill-red-filter',
        'status' => 'active',
        'is_active' => true,
    ]);

    $mechanicalDefinition = Definition::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Torque',
        'code' => 'torque_filter',
        'kind' => 'specification',
        'value_type' => 'number',
        'group_name' => 'technical',
        'section_name' => 'mechanical',
        'is_active' => true,
    ]);

    $mechanicalDefinition->targets()->create([
        'target_type' => 'variant',
    ]);

    $presentationDefinition = Definition::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Finish',
        'code' => 'finish_filter',
        'kind' => 'attribute',
        'value_type' => 'text',
        'group_name' => 'presentation',
        'section_name' => 'finish',
        'is_active' => true,
    ]);

    $presentationDefinition->targets()->create([
        'target_type' => 'variant',
    ]);

    $attributeDefinition = Definition::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Torque Label',
        'code' => 'torque_label_filter',
        'kind' => 'attribute',
        'value_type' => 'text',
        'group_name' => 'technical',
        'section_name' => 'mechanical',
        'is_active' => true,
    ]);

    $attributeDefinition->targets()->create([
        'target_type' => 'variant',
    ]);

    $matchingVariant->values()->create([
        'tenant_id' => $this->tenant->id,
        'definition_id' => $mechanicalDefinition->id,
        'value_number' => 45,
        'normalized_number' => 45,
        'channel' => 'web',
    ]);

    $nonMatchingVariant->values()->create([
        'tenant_id' => $this->tenant->id,
        'definition_id' => $presentationDefinition->id,
        'value_text' => 'Matte',
        'channel' => 'web',
    ]);

    $nonMatchingVariant->values()->create([
        'tenant_id' => $this->tenant->id,
        'definition_id' => $attributeDefinition->id,
        'value_text' => 'High Torque',
        'channel' => 'web',
    ]);

    $this->getJson('/api/v1/variants?product_id=' . $this->product->id . '&definition_group_name=technical&definition_section_name=mechanical&definition_kind=specification')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.uuid', $matchingVariant->uuid);
});
