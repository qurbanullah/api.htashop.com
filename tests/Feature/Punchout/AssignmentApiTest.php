<?php

use App\Http\Middleware\ApiAuthenticate;
use App\Models\Assignment;
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
        'status' => 'active',
        'is_active' => true,
    ]);

    $this->actingAs($this->user, 'api');
});

it('creates and shows an assignment for a product', function () {
    $storeResponse = $this->postJson('/api/v1/assignments', [
        'tenant_id' => $this->tenant->id,
        'organization_id' => $this->organization->id,
        'assignable_type' => 'product',
        'assignable_uuid' => $this->product->uuid,
        'role' => 'vendor',
        'is_primary' => true,
        'metadata' => ['channel' => 'catalog'],
    ]);

    $storeResponse
        ->assertStatus(201)
        ->assertJson([
            'success' => true,
            'message' => 'Assignment created successfully',
            'data' => [
                'tenant_id' => $this->tenant->id,
                'organization_id' => $this->organization->id,
                'role' => 'vendor',
                'is_primary' => true,
                'metadata' => ['channel' => 'catalog'],
                'assignable' => [
                    'type' => 'product',
                    'uuid' => $this->product->uuid,
                    'name' => 'Punchout Drill',
                ],
            ],
        ]);

    $id = $storeResponse->json('data.id');

    $this->getJson('/api/v1/assignments/' . $id)
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Assignment retrieved successfully',
            'data' => [
                'id' => $id,
                'role' => 'vendor',
            ],
        ]);

    expect(Assignment::count())->toBe(1);
});

it('lists assignments and updates one', function () {
    $assignment = $this->product->assignments()->create([
        'tenant_id' => $this->tenant->id,
        'organization_id' => $this->organization->id,
        'role' => 'vendor',
        'is_primary' => false,
        'metadata' => ['channel' => 'erp'],
    ]);

    $this->product->assignments()->create([
        'tenant_id' => $this->tenant->id,
        'organization_id' => $this->organization->id,
        'role' => 'reseller',
        'is_primary' => false,
    ]);

    $this->getJson('/api/v1/assignments?tenant_id=' . $this->tenant->id . '&assignable_type=product&assignable_uuid=' . $this->product->uuid)
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Assignments retrieved successfully',
        ])
        ->assertJsonCount(2, 'data');

    $this->putJson('/api/v1/assignments/' . $assignment->id, [
        'role' => 'seller',
        'is_primary' => true,
        'metadata' => ['channel' => 'catalog'],
    ])
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Assignment updated successfully',
            'data' => [
                'id' => $assignment->id,
                'role' => 'seller',
                'is_primary' => true,
                'metadata' => ['channel' => 'catalog'],
            ],
        ]);
});

it('deletes an assignment', function () {
    $assignment = $this->product->assignments()->create([
        'tenant_id' => $this->tenant->id,
        'organization_id' => $this->organization->id,
        'role' => 'vendor',
        'is_primary' => true,
    ]);

    $this->deleteJson('/api/v1/assignments/' . $assignment->id)
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Assignment deleted successfully',
            'data' => null,
        ]);

    $this->assertDatabaseMissing('assignments', ['id' => $assignment->id]);
});

it('rejects an assignable record from another tenant', function () {
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
        'status' => 'active',
        'is_active' => true,
    ]);

    $this->postJson('/api/v1/assignments', [
        'tenant_id' => $this->tenant->id,
        'organization_id' => $this->organization->id,
        'assignable_type' => 'product',
        'assignable_uuid' => $foreignProduct->uuid,
        'role' => 'vendor',
    ])
        ->assertStatus(422)
        ->assertJson([
            'success' => false,
            'status_code' => 422,
        ]);

    expect(Assignment::count())->toBe(0);
});

it('rejects creating an assignment for a user without an active membership', function () {
    $intruder = User::factory()->create();

    $this->actingAs($intruder, 'api');

    $this->postJson('/api/v1/assignments', [
        'tenant_id' => $this->tenant->id,
        'organization_id' => $this->organization->id,
        'assignable_type' => 'product',
        'assignable_uuid' => $this->product->uuid,
        'role' => 'vendor',
    ])
        ->assertStatus(403)
        ->assertJsonPath('success', false);

    expect(Assignment::count())->toBe(0);
});

it('rejects deleting an assignment from another organization membership', function () {
    $assignment = $this->product->assignments()->create([
        'tenant_id' => $this->tenant->id,
        'organization_id' => $this->organization->id,
        'role' => 'vendor',
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

    $this->deleteJson('/api/v1/assignments/' . $assignment->id)
        ->assertStatus(403)
        ->assertJsonPath('success', false);

    $this->assertDatabaseHas('assignments', ['id' => $assignment->id]);
});
