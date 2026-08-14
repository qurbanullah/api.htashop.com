<?php

use App\Models\Dam;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Version;
use App\Services\Dam\DamService;
use App\Services\Storage\S3UploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;

it('generates a presigned upload url for authenticated clients', function () {
    $user = User::factory()->create();

    $this->mock(S3UploadService::class, function (MockInterface $mock) {
        $mock->shouldReceive('generateUniqueKey')
            ->once()
            ->with('images/versions', 'release.zip', true)
            ->andReturn('images/versions/generated-release.zip');

        $mock->shouldReceive('generatePresignedUploadUrl')
            ->once()
            ->with('images/versions/generated-release.zip', 'application/zip', 600, 1024)
            ->andReturn([
                'url' => 'https://uploads.example.test/presigned',
                'key' => 'images/versions/generated-release.zip',
                'bucket' => 'test-bucket',
                'cache_control' => 'max-age=31536000',
            ]);
    });

    $response = $this
        ->actingAs($user, 'api')
        ->postJson('/api/v1/dam/upload/presigned-url', [
            'filename' => 'release.zip',
            'content_type' => 'application/zip',
            'directory' => 'images/versions',
            'max_file_size' => 1024,
            'expires_in' => 600,
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.url', 'https://uploads.example.test/presigned')
        ->assertJsonPath('data.key', 'images/versions/generated-release.zip')
        ->assertJsonPath('data.bucket', 'test-bucket')
        ->assertJsonPath('data.original_filename', 'release.zip');
});

it('handles the multipart upload lifecycle through dam endpoints', function () {
    $user = User::factory()->create();

    $this->mock(S3UploadService::class, function (MockInterface $mock) {
        $mock->shouldReceive('generateUniqueKey')
            ->once()
            ->with('downloads/releases', 'bundle.zip', true)
            ->andReturn('downloads/releases/generated-bundle.zip');

        $mock->shouldReceive('initiateMultipartUpload')
            ->once()
            ->with('downloads/releases/generated-bundle.zip', 'application/zip')
            ->andReturn([
                'upload_id' => 'upload-123',
                'key' => 'downloads/releases/generated-bundle.zip',
                'bucket' => 'test-bucket',
            ]);

        $mock->shouldReceive('generateMultipartUploadUrl')
            ->twice()
            ->andReturnUsing(function (string $key, string $uploadId, int $partNumber, int $expiresIn) {
                return "https://uploads.example.test/{$uploadId}/{$partNumber}?expires={$expiresIn}&key={$key}";
            });

        $mock->shouldReceive('completeMultipartUpload')
            ->once()
            ->with('downloads/releases/generated-bundle.zip', 'upload-123', [
                ['PartNumber' => 1, 'ETag' => 'etag-1'],
                ['PartNumber' => 2, 'ETag' => 'etag-2'],
            ])
            ->andReturn([
                'location' => 'https://cdn.example.test/downloads/releases/generated-bundle.zip',
                'key' => 'downloads/releases/generated-bundle.zip',
                'bucket' => 'test-bucket',
            ]);

        $mock->shouldReceive('getFileMetadata')
            ->once()
            ->with('downloads/releases/generated-bundle.zip')
            ->andReturn([
                'content_type' => 'application/zip',
                'size' => 12_500_000,
                'etag' => 'etag-complete',
            ]);

        $mock->shouldReceive('abortMultipartUpload')
            ->once()
            ->with('downloads/releases/generated-bundle.zip', 'upload-123');
    });

    $this->actingAs($user, 'api');

    $initiateResponse = $this->postJson('/api/v1/dam/upload/multipart/initiate', [
        'filename' => 'bundle.zip',
        'content_type' => 'application/zip',
        'directory' => 'downloads/releases',
        'file_size' => 12_500_000,
        'chunk_size' => 6_250_000,
    ]);

    $initiateResponse
        ->assertOk()
        ->assertJsonPath('data.upload_id', 'upload-123')
        ->assertJsonPath('data.key', 'downloads/releases/generated-bundle.zip')
        ->assertJsonPath('data.total_parts', 2);

    $partUrlsResponse = $this->postJson('/api/v1/dam/upload/multipart/part-urls', [
        'key' => 'downloads/releases/generated-bundle.zip',
        'upload_id' => 'upload-123',
        'part_numbers' => [1, 2],
        'expires_in' => 900,
    ]);

    $partUrlsResponse
        ->assertOk()
        ->assertJsonPath('data.urls.0.part_number', 1)
        ->assertJsonPath('data.urls.1.part_number', 2);

    $completeResponse = $this->postJson('/api/v1/dam/upload/multipart/complete', [
        'key' => 'downloads/releases/generated-bundle.zip',
        'upload_id' => 'upload-123',
        'parts' => [
            ['PartNumber' => 1, 'ETag' => 'etag-1'],
            ['PartNumber' => 2, 'ETag' => 'etag-2'],
        ],
    ]);

    $completeResponse
        ->assertOk()
        ->assertJsonPath('data.location', 'https://cdn.example.test/downloads/releases/generated-bundle.zip')
        ->assertJsonPath('data.metadata.content_type', 'application/zip')
        ->assertJsonPath('data.metadata.size', 12_500_000);

    $abortResponse = $this->postJson('/api/v1/dam/upload/multipart/abort', [
        'key' => 'downloads/releases/generated-bundle.zip',
        'upload_id' => 'upload-123',
    ]);

    $abortResponse
        ->assertOk()
        ->assertJsonPath('success', true);
});

it('verifies uploads and generates authenticated access urls through both dam and compatibility routes', function () {
    $user = User::factory()->create();

    $this->mock(S3UploadService::class, function (MockInterface $mock) {
        $mock->shouldReceive('fileExists')
            ->once()
            ->with('images/versions/banner.png', 2, 100)
            ->andReturn(true);

        $mock->shouldReceive('getFileMetadata')
            ->once()
            ->with('images/versions/banner.png', 2, 100)
            ->andReturn([
                'content_type' => 'image/png',
                'size' => 4096,
                'etag' => 'etag-banner',
            ]);

        $mock->shouldReceive('fileExists')
            ->once()
            ->with('images/versions/banner.png')
            ->andReturn(true);

        $mock->shouldReceive('generatePresignedDownloadUrl')
            ->once()
            ->with('images/versions/banner.png', 900, 'banner.png')
            ->andReturn('https://downloads.example.test/banner.png');
    });

    $this->actingAs($user, 'api');

    $verifyResponse = $this->postJson('/api/v1/dam/verify', [
        'key' => 'images/versions/banner.png',
    ]);

    $verifyResponse
        ->assertOk()
        ->assertJsonPath('data.exists', true)
        ->assertJsonPath('data.metadata.content_type', 'image/png');

    $accessUrlResponse = $this->postJson('/api/v1/storage/generate-url', [
        'key' => 'images/versions/banner.png',
        'filename' => 'banner.png',
        'expires_in' => 900,
    ]);

    $accessUrlResponse
        ->assertOk()
        ->assertJsonPath('data.url', 'https://downloads.example.test/banner.png')
        ->assertJsonPath('data.key', 'images/versions/banner.png');
});

it('rejects raw storage verification and download url generation for a DAM key owned by another organization', function () {
    $user = User::factory()->create();

    $tenant = Tenant::create([
        'name' => 'Acme Tenant',
        'slug' => 'acme-tenant',
        'is_active' => true,
    ]);

    $allowedOrganization = Organization::create([
        'tenant_id' => $tenant->id,
        'name' => 'Allowed Vendor',
        'slug' => 'allowed-vendor',
        'type' => 'vendor',
        'is_active' => true,
    ]);

    $blockedOrganization = Organization::create([
        'tenant_id' => $tenant->id,
        'name' => 'Blocked Vendor',
        'slug' => 'blocked-vendor',
        'type' => 'vendor',
        'is_active' => true,
    ]);

    Membership::create([
        'tenant_id' => $tenant->id,
        'organization_id' => $allowedOrganization->id,
        'user_id' => $user->id,
        'role' => 'owner',
        'is_primary' => true,
        'is_active' => true,
    ]);

    $blockedProduct = Product::create([
        'tenant_id' => $tenant->id,
        'organization_id' => $blockedOrganization->id,
        'name' => 'Blocked Drill',
        'slug' => 'blocked-drill',
        'status' => 'active',
        'is_active' => true,
    ]);

    Dam::create([
        'uuid' => (string) \Illuminate\Support\Str::uuid(),
        'damable_type' => Product::class,
        'damable_id' => $blockedProduct->id,
        'collection_name' => 'image',
        'file_name' => 'blocked.png',
        'disk' => 'idrivee2',
        'bucket' => 'test-bucket',
        'object_key' => 'images/products/blocked.png',
        'mime_type' => 'image/png',
        'size' => 1024,
        'is_current' => true,
    ]);

    $this->mock(S3UploadService::class, function (MockInterface $mock) {
        $mock->shouldNotReceive('fileExists');
        $mock->shouldNotReceive('generatePresignedDownloadUrl');
        $mock->shouldNotReceive('getFileMetadata');
    });

    $this->actingAs($user, 'api');

    $this->postJson('/api/v1/dam/verify', ['key' => 'images/products/blocked.png'])
        ->assertForbidden();

    $this->postJson('/api/v1/storage/generate-url', [
        'key' => 'images/products/blocked.png',
        'filename' => 'blocked.png',
    ])
        ->assertForbidden();
});

it('ingests a dam record and enriches it with storage metadata', function () {
    $user = User::factory()->create();
    $version = Version::factory()->create();

    $this->mock(S3UploadService::class, function (MockInterface $mock) {
        $mock->shouldReceive('getFileMetadata')
            ->once()
            ->with('images/versions/release-banner.png', 2, 100)
            ->andReturn([
                'content_type' => 'image/png',
                'size' => 2048,
                'etag' => 'etag-ingest',
                'custom' => 'value',
            ]);
    });

    $response = $this
        ->actingAs($user, 'api')
        ->postJson('/api/v1/dam/ingest', [
            'object_key' => 'images/versions/release-banner.png',
            'file_name' => 'release-banner.png',
            'collection_name' => 'image',
            'damable_type' => Version::class,
            'damable_id' => $version->id,
        ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.object_key', 'images/versions/release-banner.png')
        ->assertJsonPath('data.mime_type', 'image/png')
        ->assertJsonPath('data.size', 2048)
        ->assertJsonPath('data.uploaded_by', $user->id);

    $dam = Dam::query()->latest('id')->first();

    expect($dam)->not->toBeNull();
    expect($dam->damable_type)->toBe(Version::class);
    expect($dam->damable_id)->toBe($version->id);
    expect($dam->etag)->toBe('etag-ingest');
});

it('generates public asset urls without authentication for image keys only', function () {
    $this->mock(DamService::class, function (MockInterface $mock) {
        $mock->shouldReceive('generateAccessUrl')
            ->once()
            ->with('images/versions/banner.png', 120)
            ->andReturn([
                'url' => 'https://public.example.test/images/versions/banner.png',
            ]);
    });

    $response = $this->postJson('/api/v1/assets/generate-url', [
        'key' => 'images/versions/banner.png',
        'expires_in' => 120,
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.url', 'https://public.example.test/images/versions/banner.png');
});

it('rejects non image public asset requests', function () {
    $response = $this->postJson('/api/v1/assets/generate-url', [
        'key' => 'downloads/releases/release.zip',
    ]);

    $response
        ->assertStatus(403)
        ->assertJsonPath('success', false);
});

it('scopes dam owner lookup to the authenticated membership organization', function () {
    $user = User::factory()->create();

    $tenant = Tenant::create([
        'name' => 'Acme Tenant',
        'slug' => 'acme-tenant',
        'is_active' => true,
    ]);

    $allowedOrganization = Organization::create([
        'tenant_id' => $tenant->id,
        'name' => 'Allowed Vendor',
        'slug' => 'allowed-vendor',
        'type' => 'vendor',
        'is_active' => true,
    ]);

    $blockedOrganization = Organization::create([
        'tenant_id' => $tenant->id,
        'name' => 'Blocked Vendor',
        'slug' => 'blocked-vendor',
        'type' => 'vendor',
        'is_active' => true,
    ]);

    Membership::create([
        'tenant_id' => $tenant->id,
        'organization_id' => $allowedOrganization->id,
        'user_id' => $user->id,
        'role' => 'owner',
        'is_primary' => true,
        'is_active' => true,
    ]);

    Product::create([
        'tenant_id' => $tenant->id,
        'organization_id' => $allowedOrganization->id,
        'name' => 'Allowed Drill',
        'slug' => 'allowed-drill',
        'status' => 'active',
        'is_active' => true,
    ]);

    Product::create([
        'tenant_id' => $tenant->id,
        'organization_id' => $blockedOrganization->id,
        'name' => 'Blocked Drill',
        'slug' => 'blocked-drill',
        'status' => 'active',
        'is_active' => true,
    ]);

    $response = $this
        ->actingAs($user, 'api')
        ->getJson('/api/v1/dam/owners?type=product&query=drill');

    $response
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Allowed Drill');

    expect($response->json('data'))->toHaveCount(1);
});

it('rejects dam asset listing for products outside the authenticated membership organization', function () {
    $user = User::factory()->create();

    $tenant = Tenant::create([
        'name' => 'Acme Tenant',
        'slug' => 'acme-tenant',
        'is_active' => true,
    ]);

    $allowedOrganization = Organization::create([
        'tenant_id' => $tenant->id,
        'name' => 'Allowed Vendor',
        'slug' => 'allowed-vendor',
        'type' => 'vendor',
        'is_active' => true,
    ]);

    $blockedOrganization = Organization::create([
        'tenant_id' => $tenant->id,
        'name' => 'Blocked Vendor',
        'slug' => 'blocked-vendor',
        'type' => 'vendor',
        'is_active' => true,
    ]);

    Membership::create([
        'tenant_id' => $tenant->id,
        'organization_id' => $allowedOrganization->id,
        'user_id' => $user->id,
        'role' => 'owner',
        'is_primary' => true,
        'is_active' => true,
    ]);

    $blockedProduct = Product::create([
        'tenant_id' => $tenant->id,
        'organization_id' => $blockedOrganization->id,
        'name' => 'Blocked Drill',
        'slug' => 'blocked-drill',
        'status' => 'active',
        'is_active' => true,
    ]);

    Dam::create([
        'uuid' => (string) \Illuminate\Support\Str::uuid(),
        'damable_type' => Product::class,
        'damable_id' => $blockedProduct->id,
        'collection_name' => 'image',
        'file_name' => 'blocked.png',
        'disk' => 'idrivee2',
        'bucket' => 'test-bucket',
        'object_key' => 'images/products/blocked.png',
        'mime_type' => 'image/png',
        'size' => 1024,
        'is_current' => true,
    ]);

    $this
        ->actingAs($user, 'api')
        ->getJson('/api/v1/dam/assets?damable_type=' . urlencode(Product::class) . '&damable_id=' . $blockedProduct->id)
        ->assertForbidden();
});

it('rejects dam ingest for products outside the authenticated membership organization', function () {
    $user = User::factory()->create();

    $tenant = Tenant::create([
        'name' => 'Acme Tenant',
        'slug' => 'acme-tenant',
        'is_active' => true,
    ]);

    $allowedOrganization = Organization::create([
        'tenant_id' => $tenant->id,
        'name' => 'Allowed Vendor',
        'slug' => 'allowed-vendor',
        'type' => 'vendor',
        'is_active' => true,
    ]);

    $blockedOrganization = Organization::create([
        'tenant_id' => $tenant->id,
        'name' => 'Blocked Vendor',
        'slug' => 'blocked-vendor',
        'type' => 'vendor',
        'is_active' => true,
    ]);

    Membership::create([
        'tenant_id' => $tenant->id,
        'organization_id' => $allowedOrganization->id,
        'user_id' => $user->id,
        'role' => 'owner',
        'is_primary' => true,
        'is_active' => true,
    ]);

    $blockedProduct = Product::create([
        'tenant_id' => $tenant->id,
        'organization_id' => $blockedOrganization->id,
        'name' => 'Blocked Drill',
        'slug' => 'blocked-drill',
        'status' => 'active',
        'is_active' => true,
    ]);

    $this->mock(S3UploadService::class, function (MockInterface $mock) {
        $mock->shouldNotReceive('getFileMetadata');
    });

    $this
        ->actingAs($user, 'api')
        ->postJson('/api/v1/dam/ingest', [
            'object_key' => 'images/products/blocked.png',
            'file_name' => 'blocked.png',
            'collection_name' => 'image',
            'damable_type' => Product::class,
            'damable_id' => $blockedProduct->id,
        ])
        ->assertForbidden();
});
