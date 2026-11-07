<?php

namespace Tests\Feature\Dam;

use App\Models\Dam;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Str;

function productDamOwner(): Product
{
    $tenant = Tenant::create([
        'name' => 'Acme Industrial',
        'slug' => 'acme-industrial-'.Str::lower(Str::random(6)),
        'is_active' => true,
    ]);

    $organization = Organization::create([
        'tenant_id' => $tenant->id,
        'name' => 'Acme Power Division',
        'slug' => 'acme-power-division-'.Str::lower(Str::random(6)),
        'type' => 'vendor',
        'is_active' => true,
    ]);

    return Product::create([
        'tenant_id' => $tenant->id,
        'organization_id' => $organization->id,
        'name' => 'Test Drill',
        'slug' => 'test-drill-'.Str::lower(Str::random(6)),
        'status' => 'active',
        'is_active' => true,
    ]);
}

function productDamAsset(Product $product, string $collection = 'gallery', int $sortOrder = 0): Dam
{
    return Dam::create([
        'uuid' => (string) Str::uuid(),
        'damable_type' => Product::class,
        'damable_id' => $product->id,
        'collection_name' => $collection,
        'sort_order' => $sortOrder,
        'file_name' => 'gallery-'.$sortOrder.'.jpg',
        'object_key' => 'products/'.$product->uuid.'/images/gallery-'.$sortOrder.'.jpg',
        'mime_type' => 'image/jpeg',
        'is_current' => true,
        'version' => 1,
    ]);
}

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->product = productDamOwner();
    $this->asset = productDamAsset($this->product);

    $this->actingAs($this->user, 'api');
});

it('moves an asset between collections and updates its position', function () {
    $this->putJson("/api/v1/dam/{$this->asset->id}", [
        'collection_name' => 'featured',
        'sort_order' => 0,
    ])
        ->assertOk()
        ->assertJsonPath('data.collection_name', 'featured')
        ->assertJsonPath('data.sort_order', 0);

    $this->assertDatabaseHas('dams', [
        'id' => $this->asset->id,
        'collection_name' => 'featured',
        'sort_order' => 0,
    ]);
});

it('reorders gallery assets without changing their collection', function () {
    $second = productDamAsset($this->product, 'gallery', 1);

    $this->putJson("/api/v1/dam/{$second->id}", ['sort_order' => 0])->assertOk();

    $this->assertDatabaseHas('dams', [
        'id' => $second->id,
        'collection_name' => 'gallery',
        'sort_order' => 0,
    ]);
});

it('validates the sort order', function () {
    $this->putJson("/api/v1/dam/{$this->asset->id}", ['sort_order' => -1])
        ->assertStatus(422)
        ->assertJsonValidationErrors('sort_order');
});

it('soft deletes an asset', function () {
    $this->deleteJson("/api/v1/dam/{$this->asset->id}")->assertOk();

    $this->assertSoftDeleted('dams', ['id' => $this->asset->id]);

    $this->getJson('/api/v1/dam/assets?damable_type='.urlencode(Product::class)."&damable_id={$this->product->id}")
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('returns not found for an unknown asset', function () {
    $this->putJson('/api/v1/dam/999999', ['collection_name' => 'featured'])->assertNotFound();

    $this->deleteJson('/api/v1/dam/999999')->assertNotFound();
});

it('merges metadata updates without dropping existing keys', function () {
    $this->asset->update(['metadata' => ['variants' => ['medium' => 'images/medium.jpg']]]);

    $this->putJson("/api/v1/dam/{$this->asset->id}", [
        'metadata' => ['poster_key' => 'videos/posters/poster.jpg'],
    ])->assertOk();

    $metadata = $this->asset->fresh()->metadata;

    expect($metadata['variants']['medium'])->toBe('images/medium.jpg');
    expect($metadata['poster_key'])->toBe('videos/posters/poster.jpg');
});
