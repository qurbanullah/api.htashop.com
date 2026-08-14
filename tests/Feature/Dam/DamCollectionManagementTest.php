<?php

use App\Models\Organization;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Version;
use App\Services\Storage\S3UploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;

it('creates dam collections and attaches them during ingest while preserving the stable collection name', function () {
    $user = User::factory()->create();
    $version = Version::factory()->create();

    $this->actingAs($user, 'api');

    $createImageTypeResponse = $this->postJson('/api/v1/dam/collections', [
        'key' => 'image',
        'name' => 'Image',
        'kind' => 'asset_type',
        'is_system' => true,
    ]);

    $createImageTypeResponse
        ->assertCreated()
        ->assertJsonPath('data.key', 'image')
        ->assertJsonPath('data.kind', 'asset_type');

    $createViewRoleResponse = $this->postJson('/api/v1/dam/collections', [
        'key' => 'top_view',
        'name' => 'Top View',
        'kind' => 'view_role',
    ]);

    $createViewRoleResponse
        ->assertCreated()
        ->assertJsonPath('data.key', 'top_view');

    $this->mock(S3UploadService::class, function (MockInterface $mock) {
        $mock->shouldReceive('getFileMetadata')
            ->once()
            ->with('images/products/top-view.png', 2, 100)
            ->andReturn([
                'content_type' => 'image/png',
                'size' => 4096,
                'etag' => 'etag-top-view',
            ]);
    });

    $ingestResponse = $this->postJson('/api/v1/dam/ingest', [
        'object_key' => 'images/products/top-view.png',
        'file_name' => 'top-view.png',
        'collection_name' => 'image',
        'collection_keys' => ['top_view'],
        'custom_properties' => [
            'alt' => 'Top view render',
        ],
        'sort_order' => 2,
        'damable_type' => Version::class,
        'damable_id' => $version->id,
    ]);

    $ingestResponse
        ->assertCreated()
        ->assertJsonPath('data.collection_name', 'image')
        ->assertJsonPath('data.sort_order', 2)
        ->assertJsonPath('data.custom_properties.alt', 'Top view render')
        ->assertJsonPath('data.collections.0.key', 'image')
        ->assertJsonPath('data.collections.1.key', 'top_view');
});

it('allows newly damable catalog models to attach labeled assets and resolve a primary image', function () {
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
        'collection_keys' => ['primary', 'front_view'],
    ], 'image');

    $product->attachDam([
        'uuid' => (string) \Illuminate\Support\Str::uuid(),
        'file_name' => 'top-view.png',
        'disk' => config('dam.default_disk'),
        'bucket' => config('dam.default_bucket'),
        'object_key' => 'images/products/top-view.png',
        'is_current' => true,
        'sort_order' => 2,
        'collection_keys' => ['top_view'],
    ], 'image');

    $primaryAsset = $product->primaryDamAsset('image');
    $topViewAsset = $product->currentDamAsset('image', 'top_view');

    expect($primaryAsset)->not->toBeNull();
    expect($primaryAsset?->object_key)->toBe('images/products/front-view.png');
    expect($primaryAsset?->hasCollectionKey('primary'))->toBeTrue();
    expect($topViewAsset?->object_key)->toBe('images/products/top-view.png');
    expect($product->dams()->where('collection_name', 'image')->count())->toBe(2);
});

it('deletes an unused dam collection and blocks deleting one attached to assets', function () {
    $user = User::factory()->create();
    $version = Version::factory()->create();

    $attachedCollection = $this
        ->actingAs($user, 'api')
        ->postJson('/api/v1/dam/collections', [
            'key' => 'left_view',
            'name' => 'Left View',
            'kind' => 'view_role',
        ])
        ->assertCreated()
        ->json('data');

    $unusedCollection = $this
        ->postJson('/api/v1/dam/collections', [
            'key' => 'unused_label',
            'name' => 'Unused Label',
            'kind' => 'label',
        ])
        ->assertCreated()
        ->json('data');

    $this->mock(S3UploadService::class, function (MockInterface $mock) {
        $mock->shouldReceive('getFileMetadata')
            ->once()
            ->with('images/products/left-view.png', 2, 100)
            ->andReturn([
                'content_type' => 'image/png',
                'size' => 2048,
                'etag' => 'etag-left-view',
            ]);
    });

    $this->postJson('/api/v1/dam/ingest', [
        'object_key' => 'images/products/left-view.png',
        'file_name' => 'left-view.png',
        'collection_name' => 'image',
        'collection_keys' => ['left_view'],
        'damable_type' => Version::class,
        'damable_id' => $version->id,
    ])->assertCreated();

    $this->deleteJson('/api/v1/dam/collections/' . $unusedCollection['id'])
        ->assertOk();

    $this->deleteJson('/api/v1/dam/collections/' . $attachedCollection['id'])
        ->assertStatus(422)
        ->assertJsonPath('data.collection.0', 'DAM collection is attached to one or more assets and cannot be deleted.');
});
