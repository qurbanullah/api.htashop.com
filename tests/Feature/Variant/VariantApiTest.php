<?php

namespace Tests\Feature\Variant;

use App\Http\Middleware\ApiAuthenticate;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Variant;
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

    $this->product = Product::create([
        'tenant_id' => $this->tenant->id,
        'organization_id' => $this->organization->id,
        'name' => 'Test Drill',
        'slug' => 'test-drill',
        'status' => 'active',
        'is_active' => true,
    ]);

    $this->actingAs($this->user, 'api');
});

it('creates and shows a variant', function () {
    $storeResponse = $this->postJson('/api/v1/variants', [
        'product_id' => $this->product->id,
        'name' => 'Test Drill Blue',
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
                'name' => 'Test Drill Blue',
                'slug' => 'test-drill-blue',
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

    $this->getJson('/api/v1/variants/'.$uuid)
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Variant retrieved successfully',
            'data' => [
                'uuid' => $uuid,
                'slug' => 'test-drill-blue',
            ],
        ]);
});

it('lists product variants and updates one', function () {
    $variant = Variant::create([
        'product_id' => $this->product->id,
        'name' => 'Test Drill Blue',
        'slug' => 'test-drill-blue',
        'status' => 'draft',
        'configuration' => ['color' => 'blue'],
        'is_default' => false,
        'is_active' => true,
    ]);

    Variant::create([
        'product_id' => $this->product->id,
        'name' => 'Test Drill Red',
        'slug' => 'test-drill-red',
        'status' => 'active',
        'configuration' => ['color' => 'red'],
        'is_default' => true,
        'is_active' => true,
    ]);

    $this->getJson('/api/v1/variants?product_id='.$this->product->id)
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Variants retrieved successfully',
        ])
        ->assertJsonCount(2, 'data');

    $this->putJson('/api/v1/variants/'.$variant->uuid, [
        'name' => 'Test Drill Blue Pro',
        'configuration' => ['color' => 'blue', 'size' => 'pro'],
        'is_default' => true,
    ])
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Variant updated successfully',
            'data' => [
                'uuid' => $variant->uuid,
                'name' => 'Test Drill Blue Pro',
                'slug' => 'test-drill-blue-pro',
                'configuration' => ['color' => 'blue', 'size' => 'pro'],
                'is_default' => true,
            ],
        ]);
});

it('soft deletes a variant', function () {
    $variant = Variant::create([
        'product_id' => $this->product->id,
        'name' => 'Test Drill Blue',
        'slug' => 'test-drill-blue',
        'status' => 'draft',
        'is_active' => true,
    ]);

    $this->deleteJson('/api/v1/variants/'.$variant->uuid)
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Variant deleted successfully',
            'data' => null,
        ]);

    $this->assertSoftDeleted('variants', ['id' => $variant->id]);
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
        'name' => 'Test Drill Blue',
        'slug' => 'test-drill-blue',
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

    $this->putJson('/api/v1/variants/'.$variant->uuid, [
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
        'name' => 'Test Drill Blue',
        'slug' => 'test-drill-blue',
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

    $this->putJson('/api/v1/variants/'.$variant->uuid, [
        'product_id' => $siblingProduct->id,
    ])
        ->assertStatus(422)
        ->assertJson([
            'success' => false,
            'status_code' => 422,
        ]);

    expect($variant->fresh()->product_id)->toBe($this->product->id);
});

it('rejects listing variants for a user without an active membership', function () {
    $intruder = User::factory()->create();

    $this->actingAs($intruder, 'api');

    $this->getJson('/api/v1/variants')
        ->assertForbidden()
        ->assertJsonPath('success', false);
});

it('denies access to variants belonging to another organization', function () {
    $otherOrganization = Organization::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Other Variant Vendor',
        'slug' => 'other-variant-vendor',
        'type' => 'vendor',
        'is_active' => true,
    ]);

    $foreignProduct = Product::create([
        'tenant_id' => $this->tenant->id,
        'organization_id' => $otherOrganization->id,
        'name' => 'Foreign Drill',
        'slug' => 'foreign-drill-access',
        'status' => 'active',
        'is_active' => true,
    ]);

    $variant = Variant::create([
        'product_id' => $foreignProduct->id,
        'name' => 'Foreign Variant',
        'slug' => 'foreign-variant',
        'status' => 'draft',
        'is_active' => true,
    ]);

    $this->getJson('/api/v1/variants/'.$variant->uuid)->assertForbidden();
    $this->putJson('/api/v1/variants/'.$variant->uuid, ['name' => 'Hijacked'])->assertForbidden();
    $this->deleteJson('/api/v1/variants/'.$variant->uuid)->assertForbidden();

    expect($variant->fresh()->name)->toBe('Foreign Variant');
});

it('rejects creating a variant for a product in another organization', function () {
    $otherOrganization = Organization::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Blocked Variant Vendor',
        'slug' => 'blocked-variant-vendor',
        'type' => 'vendor',
        'is_active' => true,
    ]);

    $foreignProduct = Product::create([
        'tenant_id' => $this->tenant->id,
        'organization_id' => $otherOrganization->id,
        'name' => 'Blocked Drill',
        'slug' => 'blocked-drill',
        'status' => 'active',
        'is_active' => true,
    ]);

    $this->postJson('/api/v1/variants', [
        'product_id' => $foreignProduct->id,
        'name' => 'Blocked Variant',
    ])
        ->assertForbidden()
        ->assertJsonPath('success', false);

    expect(Variant::count())->toBe(0);
});

it('lists variants from every organization the member actively belongs to', function () {
    $secondOrganization = Organization::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Second Variant Vendor',
        'slug' => 'second-variant-vendor',
        'type' => 'vendor',
        'is_active' => true,
    ]);

    Membership::create([
        'tenant_id' => $this->tenant->id,
        'organization_id' => $secondOrganization->id,
        'user_id' => $this->user->id,
        'role' => 'member',
        'is_primary' => false,
        'is_active' => true,
    ]);

    Variant::create([
        'product_id' => $this->product->id,
        'name' => 'First Org Variant',
        'slug' => 'first-org-variant',
        'is_active' => true,
    ]);

    $secondProduct = Product::create([
        'tenant_id' => $this->tenant->id,
        'organization_id' => $secondOrganization->id,
        'name' => 'Second Org Drill',
        'slug' => 'second-org-drill',
        'status' => 'active',
        'is_active' => true,
    ]);

    Variant::create([
        'product_id' => $secondProduct->id,
        'name' => 'Second Org Variant',
        'slug' => 'second-org-variant',
        'is_active' => true,
    ]);

    $this->getJson('/api/v1/variants')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('does not grant variant access through an inactive membership', function () {
    $this->user->memberships()->update(['is_active' => false]);

    $this->getJson('/api/v1/variants')->assertForbidden();
});

it('lets an admin manage variants from any organization', function () {
    Role::findOrCreate('admin', 'api');

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin, 'api');

    $otherOrganization = Organization::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Admin Variant Vendor',
        'slug' => 'admin-variant-vendor',
        'type' => 'vendor',
        'is_active' => true,
    ]);

    $adminProduct = Product::create([
        'tenant_id' => $this->tenant->id,
        'organization_id' => $otherOrganization->id,
        'name' => 'Admin Drill',
        'slug' => 'admin-drill-variant',
        'status' => 'active',
        'is_active' => true,
    ]);

    $this->postJson('/api/v1/variants', [
        'product_id' => $adminProduct->id,
        'name' => 'Admin Variant',
    ])->assertStatus(201);

    $this->getJson('/api/v1/variants')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});
