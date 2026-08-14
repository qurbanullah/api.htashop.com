<?php

use App\Http\Middleware\ApiAuthenticate;
use App\Models\Code;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Tenant;
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

    $this->product = Product::create([
        'tenant_id' => $this->tenant->id,
        'organization_id' => $this->organization->id,
        'name' => 'Punchout Drill',
        'slug' => 'punchout-drill',
        'status' => 'draft',
        'is_active' => true,
    ]);

    $this->actingAs($this->user, 'api');
});

it('creates and shows a code for a product', function () {
    $storeResponse = $this->postJson('/api/v1/codes', [
        'tenant_id' => $this->tenant->id,
        'organization_id' => $this->organization->id,
        'codeable_type' => 'product',
        'codeable_uuid' => $this->product->uuid,
        'type' => 'sku',
        'value' => 'sku-001',
        'context' => 'erp',
        'is_primary' => true,
    ]);

    $storeResponse
        ->assertStatus(201)
        ->assertJson([
            'success' => true,
            'message' => 'Code created successfully',
            'data' => [
                'tenant_id' => $this->tenant->id,
                'organization_id' => $this->organization->id,
                'type' => 'sku',
                'value' => 'sku-001',
                'normalized' => 'SKU-001',
                'context' => 'erp',
                'is_primary' => true,
                'codeable' => [
                    'type' => 'product',
                    'uuid' => $this->product->uuid,
                    'name' => 'Punchout Drill',
                ],
            ],
        ]);

    $id = $storeResponse->json('data.id');

    $this->getJson('/api/v1/codes/' . $id)
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Code retrieved successfully',
            'data' => [
                'id' => $id,
                'normalized' => 'SKU-001',
            ],
        ]);
});

it('lists tenant codes and updates one', function () {
    $code = $this->product->codes()->create([
        'tenant_id' => $this->tenant->id,
        'organization_id' => $this->organization->id,
        'type' => 'sku',
        'value' => 'sku-001',
        'normalized' => 'SKU-001',
        'context' => 'erp',
        'is_primary' => false,
    ]);

    $this->product->codes()->create([
        'tenant_id' => $this->tenant->id,
        'organization_id' => $this->organization->id,
        'type' => 'ean',
        'value' => '100200300',
        'normalized' => '100200300',
        'context' => 'catalog',
        'is_primary' => false,
    ]);

    $this->getJson('/api/v1/codes?tenant_id=' . $this->tenant->id . '&codeable_type=product&codeable_uuid=' . $this->product->uuid)
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Codes retrieved successfully',
        ])
        ->assertJsonCount(2, 'data');

    $this->putJson('/api/v1/codes/' . $code->id, [
        'value' => 'sku-777',
        'is_primary' => true,
    ])
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Code updated successfully',
            'data' => [
                'id' => $code->id,
                'value' => 'sku-777',
                'normalized' => 'SKU-777',
                'is_primary' => true,
            ],
        ]);
});

it('deletes a code', function () {
    $code = $this->product->codes()->create([
        'tenant_id' => $this->tenant->id,
        'organization_id' => $this->organization->id,
        'type' => 'sku',
        'value' => 'sku-001',
        'normalized' => 'SKU-001',
        'is_primary' => true,
    ]);

    $this->deleteJson('/api/v1/codes/' . $code->id)
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Code deleted successfully',
            'data' => null,
        ]);

    $this->assertDatabaseMissing('codes', ['id' => $code->id]);
});

it('scopes code listings to the authenticated membership organization', function () {
    $this->product->codes()->create([
        'tenant_id' => $this->tenant->id,
        'organization_id' => $this->organization->id,
        'type' => 'sku',
        'value' => 'allowed-sku',
        'normalized' => 'ALLOWED-SKU',
        'is_primary' => true,
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
        'name' => 'Sibling Product',
        'slug' => 'sibling-product',
        'status' => 'active',
        'is_active' => true,
    ]);

    $siblingProduct->codes()->create([
        'tenant_id' => $this->tenant->id,
        'organization_id' => $otherOrganization->id,
        'type' => 'sku',
        'value' => 'blocked-sku',
        'normalized' => 'BLOCKED-SKU',
        'is_primary' => true,
    ]);

    $response = $this->getJson('/api/v1/codes?tenant_id=' . $this->tenant->id);

    $response
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.value', 'allowed-sku');
});

it('rejects a codeable record from another tenant', function () {
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
        'name' => 'Foreign Product',
        'slug' => 'foreign-product',
        'status' => 'draft',
        'is_active' => true,
    ]);

    $this->postJson('/api/v1/codes', [
        'tenant_id' => $this->tenant->id,
        'organization_id' => $this->organization->id,
        'codeable_type' => 'product',
        'codeable_uuid' => $foreignProduct->uuid,
        'type' => 'sku',
        'value' => 'sku-foreign',
    ])
        ->assertStatus(422)
        ->assertJson([
            'success' => false,
            'status_code' => 422,
        ]);

    expect(Code::count())->toBe(0);
});

it('rejects creating a code for a user without an active membership', function () {
    $intruder = User::factory()->create();

    $this->actingAs($intruder, 'api');

    $this->postJson('/api/v1/codes', [
        'tenant_id' => $this->tenant->id,
        'organization_id' => $this->organization->id,
        'codeable_type' => 'product',
        'codeable_uuid' => $this->product->uuid,
        'type' => 'sku',
        'value' => 'sku-unauthorized',
    ])
        ->assertStatus(403)
        ->assertJsonPath('success', false);

    expect(Code::count())->toBe(0);
});

it('rejects updating a code from another organization membership', function () {
    $code = $this->product->codes()->create([
        'tenant_id' => $this->tenant->id,
        'organization_id' => $this->organization->id,
        'type' => 'sku',
        'value' => 'sku-001',
        'normalized' => 'SKU-001',
        'is_primary' => true,
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

    $this->putJson('/api/v1/codes/' . $code->id, [
        'value' => 'sku-999',
    ])
        ->assertStatus(403)
        ->assertJsonPath('success', false);

    expect($code->fresh()->value)->toBe('sku-001');
});

it('rejects deleting a code from another organization membership', function () {
    $code = $this->product->codes()->create([
        'tenant_id' => $this->tenant->id,
        'organization_id' => $this->organization->id,
        'type' => 'sku',
        'value' => 'sku-protected',
        'normalized' => 'SKU-PROTECTED',
        'is_primary' => true,
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

    $this->deleteJson('/api/v1/codes/' . $code->id)
        ->assertStatus(403)
        ->assertJsonPath('success', false);

    $this->assertDatabaseHas('codes', [
        'id' => $code->id,
        'value' => 'sku-protected',
    ]);
});
