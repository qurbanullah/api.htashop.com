<?php

use App\Models\Organization;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Variant;
use Illuminate\Foundation\Testing\RefreshDatabase;

it('searches dam owners by type and query', function () {
    $user = User::factory()->create();

    $tenant = Tenant::create([
        'name' => 'Acme Industrial',
        'slug' => 'acme-industrial',
        'domain' => 'acme.test',
        'is_active' => true,
    ]);

    $organization = Organization::create([
        'tenant_id' => $tenant->id,
        'name' => 'Acme Power Division',
        'slug' => 'acme-power-division',
        'type' => 'vendor',
        'is_active' => true,
    ]);

    $product = Product::create([
        'tenant_id' => $tenant->id,
        'organization_id' => $organization->id,
        'name' => 'Punchout Drill',
        'slug' => 'punchout-drill',
        'status' => 'active',
        'is_active' => true,
    ]);

    $variant = Variant::create([
        'product_id' => $product->id,
        'name' => 'Punchout Drill Blue',
        'slug' => 'punchout-drill-blue',
        'status' => 'active',
        'is_active' => true,
    ]);

    $this
        ->actingAs($user, 'api')
        ->getJson('/api/v1/dam/owners?type=product&query=drill')
        ->assertOk()
        ->assertJsonPath('data.0.type', 'product')
        ->assertJsonPath('data.0.id', $product->id)
        ->assertJsonPath('data.0.name', 'Punchout Drill')
        ->assertJsonPath('data.0.uuid', $product->uuid);

    $this
        ->getJson('/api/v1/dam/owners?type=variant&query=blue')
        ->assertOk()
        ->assertJsonPath('data.0.type', 'variant')
        ->assertJsonPath('data.0.id', $variant->id)
        ->assertJsonPath('data.0.name', 'Punchout Drill Blue');

    $this
        ->getJson('/api/v1/dam/owners?type=organization&query=power')
        ->assertOk()
        ->assertJsonPath('data.0.type', 'organization')
        ->assertJsonPath('data.0.id', $organization->id)
        ->assertJsonPath('data.0.name', 'Acme Power Division');

    $this
        ->getJson('/api/v1/dam/owners?type=tenant&query=acme')
        ->assertOk()
        ->assertJsonPath('data.0.type', 'tenant')
        ->assertJsonPath('data.0.id', $tenant->id)
        ->assertJsonPath('data.0.name', 'Acme Industrial');
});
