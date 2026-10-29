<?php

namespace Tests\Feature\Inventory;

use App\Http\Middleware\ApiAuthenticate;
use App\Models\Inventory;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Variant;
use App\Models\Warehouse;
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

    $this->warehouse = Warehouse::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Main Warehouse',
        'code' => 'MAIN',
    ]);

    $this->actingAs($this->user, 'api');
});

function createInventoryForeignProduct(Organization $organization, Tenant $tenant, string $slug): Product
{
    return Product::create([
        'tenant_id' => $tenant->id,
        'organization_id' => $organization->id,
        'name' => 'Foreign Drill',
        'slug' => $slug,
        'status' => 'active',
        'is_active' => true,
    ]);
}

it('creates and shows an inventory record', function () {
    $storeResponse = $this->postJson('/api/v1/inventories', [
        'stockable_type' => 'product',
        'stockable_id' => $this->product->id,
        'warehouse_id' => $this->warehouse->id,
        'sku' => 'SKU-001',
        'quantity' => 12,
        'reserved' => 2,
        'low_stock_threshold' => 5,
        'track_inventory' => true,
        'is_active' => true,
    ]);

    $storeResponse
        ->assertStatus(201)
        ->assertJson([
            'success' => true,
            'message' => 'Inventory set successfully',
            'data' => [
                'tenant_id' => $this->tenant->id,
                'warehouse_id' => $this->warehouse->id,
                'stockable_type' => Product::class,
                'stockable_id' => $this->product->id,
                'sku' => 'SKU-001',
                'quantity' => '12.000',
                'reserved' => '2.000',
                'low_stock_threshold' => '5.000',
                'is_low_stock' => false,
            ],
        ]);

    $uuid = $storeResponse->json('data.uuid');

    $this->getJson('/api/v1/inventories/'.$uuid)
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Inventory retrieved successfully',
            'data' => [
                'uuid' => $uuid,
                'quantity' => '12.000',
            ],
        ]);
});

it('upserts inventory for the same stockable and warehouse', function () {
    $payload = [
        'stockable_type' => 'product',
        'stockable_id' => $this->product->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 5,
    ];

    $this->postJson('/api/v1/inventories', $payload)->assertStatus(201);

    $response = $this->postJson('/api/v1/inventories', array_merge($payload, ['quantity' => 9]));

    $response->assertStatus(201);

    expect(Inventory::count())->toBe(1);
    expect($response->json('data.quantity'))->toBe('9.000');
});

it('updates and deletes an inventory record', function () {
    $inventory = Inventory::create([
        'tenant_id' => $this->tenant->id,
        'warehouse_id' => $this->warehouse->id,
        'stockable_type' => Product::class,
        'stockable_id' => $this->product->id,
        'quantity' => 4,
    ]);

    $this->putJson('/api/v1/inventories/'.$inventory->uuid, [
        'quantity' => 7,
    ])
        ->assertOk()
        ->assertJsonPath('data.quantity', '7.000');

    $this->deleteJson('/api/v1/inventories/'.$inventory->uuid)
        ->assertOk()
        ->assertJsonPath('success', true);

    $this->assertSoftDeleted('inventories', ['id' => $inventory->id]);
});

it('lists inventory scoped to the user organization', function () {
    Inventory::create([
        'tenant_id' => $this->tenant->id,
        'warehouse_id' => $this->warehouse->id,
        'stockable_type' => Product::class,
        'stockable_id' => $this->product->id,
        'quantity' => 3,
    ]);

    $otherOrganization = Organization::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Sibling Vendor',
        'slug' => 'sibling-vendor',
        'type' => 'vendor',
        'is_active' => true,
    ]);

    $foreignProduct = createInventoryForeignProduct($otherOrganization, $this->tenant, 'foreign-drill');

    Inventory::create([
        'tenant_id' => $this->tenant->id,
        'warehouse_id' => $this->warehouse->id,
        'stockable_type' => Product::class,
        'stockable_id' => $foreignProduct->id,
        'quantity' => 99,
    ]);

    $this->getJson('/api/v1/inventories')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.stockable_id', $this->product->id);
});

it('scopes variant inventory through the product organization', function () {
    $variant = Variant::create([
        'product_id' => $this->product->id,
        'name' => 'Test Drill Blue',
        'slug' => 'test-drill-blue',
        'is_active' => true,
    ]);

    $this->postJson('/api/v1/inventories', [
        'stockable_type' => 'variant',
        'stockable_id' => $variant->id,
        'quantity' => 6,
    ])
        ->assertStatus(201)
        ->assertJsonPath('data.stockable_type', Variant::class);

    $otherOrganization = Organization::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Variant Vendor',
        'slug' => 'variant-vendor',
        'type' => 'vendor',
        'is_active' => true,
    ]);

    $foreignProduct = createInventoryForeignProduct($otherOrganization, $this->tenant, 'foreign-variant-drill');

    $foreignVariant = Variant::create([
        'product_id' => $foreignProduct->id,
        'name' => 'Foreign Variant',
        'slug' => 'foreign-variant',
        'is_active' => true,
    ]);

    $foreignInventory = Inventory::create([
        'tenant_id' => $this->tenant->id,
        'warehouse_id' => null,
        'stockable_type' => Variant::class,
        'stockable_id' => $foreignVariant->id,
        'quantity' => 42,
    ]);

    $this->getJson('/api/v1/inventories')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.stockable_id', $variant->id);

    $this->getJson('/api/v1/inventories/'.$foreignInventory->uuid)->assertForbidden();
});

it('rejects listing inventory for a user without an active membership', function () {
    $intruder = User::factory()->create();

    $this->actingAs($intruder, 'api');

    $this->getJson('/api/v1/inventories')
        ->assertForbidden()
        ->assertJsonPath('success', false);
});

it('does not grant inventory access through an inactive membership', function () {
    $this->user->memberships()->update(['is_active' => false]);

    $this->getJson('/api/v1/inventories')->assertForbidden();
});

it('denies access to inventory belonging to another organization', function () {
    $otherOrganization = Organization::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Other Inventory Vendor',
        'slug' => 'other-inventory-vendor',
        'type' => 'vendor',
        'is_active' => true,
    ]);

    $foreignProduct = createInventoryForeignProduct($otherOrganization, $this->tenant, 'other-inventory-drill');

    $inventory = Inventory::create([
        'tenant_id' => $this->tenant->id,
        'warehouse_id' => null,
        'stockable_type' => Product::class,
        'stockable_id' => $foreignProduct->id,
        'quantity' => 8,
    ]);

    $this->getJson('/api/v1/inventories/'.$inventory->uuid)->assertForbidden();
    $this->putJson('/api/v1/inventories/'.$inventory->uuid, ['quantity' => 1])->assertForbidden();
    $this->deleteJson('/api/v1/inventories/'.$inventory->uuid)->assertForbidden();

    expect($inventory->fresh()->quantity)->toBe('8.000');
});

it('rejects creating inventory for a stockable in another organization', function () {
    $otherOrganization = Organization::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Blocked Inventory Vendor',
        'slug' => 'blocked-inventory-vendor',
        'type' => 'vendor',
        'is_active' => true,
    ]);

    $foreignProduct = createInventoryForeignProduct($otherOrganization, $this->tenant, 'blocked-inventory-drill');

    $this->postJson('/api/v1/inventories', [
        'stockable_type' => 'product',
        'stockable_id' => $foreignProduct->id,
        'quantity' => 10,
    ])
        ->assertForbidden()
        ->assertJsonPath('success', false);

    expect(Inventory::count())->toBe(0);
});

it('rejects an unknown stockable or a warehouse from another tenant', function () {
    $this->postJson('/api/v1/inventories', [
        'stockable_type' => 'product',
        'stockable_id' => 999999,
        'quantity' => 1,
    ])->assertStatus(422);

    $otherTenant = Tenant::create([
        'name' => 'Other Warehouse Tenant',
        'slug' => 'other-warehouse-tenant',
        'is_active' => true,
    ]);

    $foreignWarehouse = Warehouse::create([
        'tenant_id' => $otherTenant->id,
        'name' => 'Foreign Warehouse',
        'code' => 'FOREIGN',
    ]);

    $this->postJson('/api/v1/inventories', [
        'stockable_type' => 'product',
        'stockable_id' => $this->product->id,
        'warehouse_id' => $foreignWarehouse->id,
        'quantity' => 1,
    ])
        ->assertStatus(422)
        ->assertJsonPath('success', false);

    expect(Inventory::count())->toBe(0);
});

it('lets an admin manage inventory from any organization', function () {
    Role::findOrCreate('admin', 'api');

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin, 'api');

    $otherOrganization = Organization::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Admin Inventory Vendor',
        'slug' => 'admin-inventory-vendor',
        'type' => 'vendor',
        'is_active' => true,
    ]);

    $adminProduct = createInventoryForeignProduct($otherOrganization, $this->tenant, 'admin-inventory-drill');

    $this->postJson('/api/v1/inventories', [
        'stockable_type' => 'product',
        'stockable_id' => $adminProduct->id,
        'quantity' => 20,
    ])->assertStatus(201);

    $this->getJson('/api/v1/inventories')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});
