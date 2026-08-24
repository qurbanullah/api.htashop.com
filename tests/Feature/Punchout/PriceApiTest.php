<?php

use App\Http\Middleware\ApiAuthenticate;
use App\Models\Currency;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Price;
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

    $this->currency = Currency::create([
        'code' => 'USD',
        'name' => 'US Dollar',
        'symbol' => '$',
        'precision' => 2,
        'is_active' => true,
    ]);

    $this->actingAs($this->user, 'api');
});

it('creates and shows a price for a product', function () {
    $storeResponse = $this->postJson('/api/v1/prices', [
        'tenant_id' => $this->tenant->id,
        'organization_id' => $this->organization->id,
        'currency_id' => $this->currency->id,
        'priceable_type' => 'product',
        'priceable_uuid' => $this->product->uuid,
        'type' => 'fixed',
        'base_price' => 19.99,
        'min_quantity' => 1,
        'priority' => 5,
        'is_active' => true,
    ]);

    $storeResponse
        ->assertStatus(201)
        ->assertJson([
            'success' => true,
            'message' => 'Price created successfully',
            'data' => [
                'tenant_id' => $this->tenant->id,
                'organization_id' => $this->organization->id,
                'currency_id' => $this->currency->id,
                'type' => 'fixed',
                'base_price' => '19.990000',
                'min_quantity' => '1.000000',
                'priority' => 5,
                'is_active' => true,
                'currency' => [
                    'id' => $this->currency->id,
                    'code' => 'USD',
                    'symbol' => '$',
                ],
                'priceable' => [
                    'type' => 'product',
                    'uuid' => $this->product->uuid,
                    'name' => 'Punchout Drill',
                ],
            ],
        ]);

    $id = $storeResponse->json('data.id');

    $this->getJson('/api/v1/prices/' . $id)
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Price retrieved successfully',
            'data' => [
                'id' => $id,
                'base_price' => '19.990000',
            ],
        ]);
});

it('lists tenant prices and updates one', function () {
    $price = $this->product->prices()->create([
        'tenant_id' => $this->tenant->id,
        'organization_id' => $this->organization->id,
        'currency_id' => $this->currency->id,
        'type' => 'fixed',
        'base_price' => 19.99,
        'min_quantity' => 1,
        'priority' => 5,
        'is_active' => true,
    ]);

    $this->product->prices()->create([
        'tenant_id' => $this->tenant->id,
        'organization_id' => $this->organization->id,
        'currency_id' => $this->currency->id,
        'type' => 'fixed',
        'base_price' => 29.99,
        'min_quantity' => 10,
        'priority' => 1,
        'is_active' => true,
    ]);

    $this->getJson('/api/v1/prices?tenant_id=' . $this->tenant->id . '&priceable_type=product&priceable_uuid=' . $this->product->uuid)
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Prices retrieved successfully',
        ])
        ->assertJsonCount(2, 'data');

    $this->putJson('/api/v1/prices/' . $price->id, [
        'base_price' => 24.5,
        'priority' => 10,
        'is_active' => false,
    ])
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Price updated successfully',
            'data' => [
                'id' => $price->id,
                'base_price' => '24.500000',
                'priority' => 10,
                'is_active' => false,
            ],
        ]);
});

it('deletes a price', function () {
    $price = $this->product->prices()->create([
        'tenant_id' => $this->tenant->id,
        'organization_id' => $this->organization->id,
        'currency_id' => $this->currency->id,
        'type' => 'fixed',
        'base_price' => 19.99,
        'min_quantity' => 1,
        'priority' => 5,
        'is_active' => true,
    ]);

    $this->deleteJson('/api/v1/prices/' . $price->id)
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Price deleted successfully',
            'data' => null,
        ]);

    $this->assertDatabaseMissing('prices', ['id' => $price->id]);
});

it('scopes price listings to the authenticated membership organization', function () {
    $this->product->prices()->create([
        'tenant_id' => $this->tenant->id,
        'organization_id' => $this->organization->id,
        'currency_id' => $this->currency->id,
        'type' => 'fixed',
        'base_price' => 19.99,
        'min_quantity' => 1,
        'priority' => 1,
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
        'name' => 'Sibling Product',
        'slug' => 'sibling-product',
        'status' => 'active',
        'is_active' => true,
    ]);

    $siblingProduct->prices()->create([
        'tenant_id' => $this->tenant->id,
        'organization_id' => $otherOrganization->id,
        'currency_id' => $this->currency->id,
        'type' => 'fixed',
        'base_price' => 99.99,
        'min_quantity' => 1,
        'priority' => 1,
        'is_active' => true,
    ]);

    $response = $this->getJson('/api/v1/prices?tenant_id=' . $this->tenant->id);

    $response
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.base_price', '19.990000');
});

it('rejects a maximum quantity that is lower than the minimum quantity', function () {
    $this->postJson('/api/v1/prices', [
        'tenant_id' => $this->tenant->id,
        'organization_id' => $this->organization->id,
        'currency_id' => $this->currency->id,
        'priceable_type' => 'product',
        'priceable_uuid' => $this->product->uuid,
        'type' => 'fixed',
        'base_price' => 19.99,
        'min_quantity' => 10,
        'max_quantity' => 5,
    ])
        ->assertStatus(422)
        ->assertJson([
            'success' => false,
            'status_code' => 422,
        ]);

    expect(Price::count())->toBe(0);
});

it('rejects listing prices for a user without an active membership', function () {
    $intruder = User::factory()->create();

    $this->actingAs($intruder, 'api');

    $this->getJson('/api/v1/prices?tenant_id=' . $this->tenant->id . '&priceable_type=product&priceable_uuid=' . $this->product->uuid)
        ->assertStatus(403)
        ->assertJsonPath('success', false);
});

it('rejects showing a price from another organization membership', function () {
    $price = $this->product->prices()->create([
        'tenant_id' => $this->tenant->id,
        'organization_id' => $this->organization->id,
        'currency_id' => $this->currency->id,
        'type' => 'fixed',
        'base_price' => 19.99,
        'min_quantity' => 1,
        'priority' => 5,
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

    $this->getJson('/api/v1/prices/' . $price->id)
        ->assertStatus(403)
        ->assertJsonPath('success', false);
});

it('rejects deleting a price from another organization membership', function () {
    $price = $this->product->prices()->create([
        'tenant_id' => $this->tenant->id,
        'organization_id' => $this->organization->id,
        'currency_id' => $this->currency->id,
        'type' => 'fixed',
        'base_price' => 19.99,
        'min_quantity' => 1,
        'priority' => 5,
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

    $this->deleteJson('/api/v1/prices/' . $price->id)
        ->assertStatus(403)
        ->assertJsonPath('success', false);

    $this->assertDatabaseHas('prices', [
        'id' => $price->id,
        'base_price' => '19.990000',
    ]);
});
