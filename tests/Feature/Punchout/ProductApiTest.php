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
                'status' => 'draft',
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
