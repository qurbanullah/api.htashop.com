<?php

use App\Models\Organization;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

it('lists dam assets by owner with collections and custom properties', function () {
    $user = User::factory()->create();
    $tenant = Tenant::create([
        'name' => 'Acme Tenant',
        'slug' => 'acme-tenant',
        'domain' => 'acme.test',
        'is_active' => true,
    ]);

    $organization = Organization::create([
        'tenant_id' => $tenant->id,
        'name' => 'Acme Org',
        'slug' => 'acme-org',
        'type' => 'vendor',
        'is_active' => true,
    ]);

    $product = Product::create([
        'tenant_id' => $tenant->id,
        'organization_id' => $organization->id,
        'name' => 'Render Box',
        'slug' => 'render-box',
        'status' => 'draft',
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
        'custom_properties' => ['alt' => 'Front render'],
        'collection_keys' => ['primary', 'front_view'],
    ], 'image');

    $this
        ->actingAs($user, 'api')
        ->getJson('/api/v1/dam/assets?damable_type=' . urlencode(Product::class) . '&damable_id=' . $product->id)
        ->assertOk()
        ->assertJsonPath('data.0.collection_name', 'image')
        ->assertJsonPath('data.0.custom_properties.alt', 'Front render')
        ->assertJsonPath('data.0.collections.0.key', 'image')
        ->assertJsonPath('data.0.collections.1.key', 'front_view')
        ->assertJsonPath('data.0.collections.2.key', 'primary');
});
