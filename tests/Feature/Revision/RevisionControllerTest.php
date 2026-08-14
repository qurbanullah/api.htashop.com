<?php

namespace Tests\Feature\Revision;

use App\Http\Middleware\ApiAuthenticate;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Variant;

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

test('revision controller classes exist', function () {
    expect(class_exists(\App\Http\Controllers\V1\Product\ProductRevisionController::class))->toBeTrue();
    expect(class_exists(\App\Http\Controllers\V1\Variant\VariantRevisionController::class))->toBeTrue();
});

test('product revisions can be listed and restored', function () {
    $product = Product::create([
        'tenant_id' => $this->tenant->id,
        'organization_id' => $this->organization->id,
        'name' => 'Punchout Drill',
        'slug' => 'punchout-drill',
        'status' => 'draft',
        'summary' => 'Primary drill listing',
        'description' => 'Product description',
        'is_active' => true,
    ]);

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

    $revisionsResponse = $this->getJson('/api/v1/products/' . $product->uuid . '/revisions');

    $revisionsResponse
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Product revisions retrieved successfully',
        ])
        ->assertJsonCount(1, 'data');

    $revisionUuid = $revisionsResponse->json('data.0.uuid');

    $this->getJson('/api/v1/products/' . $product->uuid . '/revisions/' . $revisionUuid)
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Revision retrieved successfully',
            'data' => [
                'uuid' => $revisionUuid,
                'revision_type' => 'update',
            ],
        ]);

    $this->postJson('/api/v1/products/' . $product->uuid . '/revisions/' . $revisionUuid . '/restore')
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Product restored successfully',
            'data' => [
                'uuid' => $product->uuid,
                'name' => 'Punchout Drill',
                'slug' => 'punchout-drill',
                'status' => 'draft',
            ],
        ]);

    $this->assertDatabaseHas('products', [
        'id' => $product->id,
        'name' => 'Punchout Drill',
        'slug' => 'punchout-drill',
        'status' => 'draft',
    ]);
});

test('variant revisions can be listed and restored', function () {
    $product = Product::create([
        'tenant_id' => $this->tenant->id,
        'organization_id' => $this->organization->id,
        'name' => 'Punchout Drill',
        'slug' => 'punchout-drill',
        'status' => 'active',
        'is_active' => true,
    ]);

    $variant = Variant::create([
        'product_id' => $product->id,
        'name' => 'Punchout Drill Blue',
        'slug' => 'punchout-drill-blue',
        'status' => 'draft',
        'summary' => 'Blue finish variant',
        'description' => 'Variant description',
        'configuration' => ['color' => 'blue'],
        'is_default' => false,
        'is_active' => true,
    ]);

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

    $revisionsResponse = $this->getJson('/api/v1/variants/' . $variant->uuid . '/revisions');

    $revisionsResponse
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Variant revisions retrieved successfully',
        ])
        ->assertJsonCount(1, 'data');

    $revisionUuid = $revisionsResponse->json('data.0.uuid');

    $this->getJson('/api/v1/variants/' . $variant->uuid . '/revisions/' . $revisionUuid)
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Revision retrieved successfully',
            'data' => [
                'uuid' => $revisionUuid,
                'revision_type' => 'update',
            ],
        ]);

    $this->postJson('/api/v1/variants/' . $variant->uuid . '/revisions/' . $revisionUuid . '/restore')
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Variant restored successfully',
            'data' => [
                'uuid' => $variant->uuid,
                'name' => 'Punchout Drill Blue',
                'slug' => 'punchout-drill-blue',
                'configuration' => ['color' => 'blue'],
                'is_default' => false,
            ],
        ]);

    $this->assertDatabaseHas('variants', [
        'id' => $variant->id,
        'name' => 'Punchout Drill Blue',
        'slug' => 'punchout-drill-blue',
        'is_default' => false,
    ]);
});
