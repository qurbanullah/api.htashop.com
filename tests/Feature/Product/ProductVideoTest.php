<?php

namespace Tests\Feature\Product;

use App\Enums\SalesChannel as SalesChannelCode;
use App\Http\Resources\V1\Product\ProductResource;
use App\Models\Dam;
use App\Models\Organization;
use App\Models\Product;
use App\Models\SalesChannel;
use App\Models\Tenant;
use Illuminate\Support\Str;

function videoTestProduct(): Product
{
    $tenant = Tenant::create([
        'name' => 'Acme Industrial',
        'slug' => 'acme-'.Str::lower(Str::random(6)),
        'is_active' => true,
    ]);

    $organization = Organization::create([
        'tenant_id' => $tenant->id,
        'name' => 'Acme Division',
        'slug' => 'acme-division-'.Str::lower(Str::random(6)),
        'type' => 'vendor',
        'is_active' => true,
    ]);

    $product = Product::create([
        'tenant_id' => $tenant->id,
        'organization_id' => $organization->id,
        'name' => 'Test Drill',
        'slug' => 'test-drill-'.Str::lower(Str::random(6)),
        'status' => 'active',
        'is_active' => true,
        'metadata' => ['price' => 100, 'currency' => 'USD'],
    ]);

    $product->salesChannels()->sync(
        SalesChannel::query()->where('code', SalesChannelCode::STOREFRONT)->pluck('id')->all()
    );

    return $product->load('salesChannels');
}

function damAsset(Product $product, string $collection, string $key, ?string $mime = null): Dam
{
    return Dam::create([
        'uuid' => (string) Str::uuid(),
        'damable_type' => Product::class,
        'damable_id' => $product->id,
        'collection_name' => $collection,
        'file_name' => basename($key),
        'object_key' => $key,
        'mime_type' => $mime,
        'size' => 2048,
        'sort_order' => 0,
        'is_current' => true,
        'version' => 1,
    ]);
}

it('returns product videos without leaking them into the image gallery', function () {
    $product = videoTestProduct();

    damAsset($product, 'featured', "products/{$product->uuid}/images/hero.jpg", 'image/jpeg');
    damAsset($product, 'gallery', "products/{$product->uuid}/images/gallery-1.jpg", 'image/jpeg');
    damAsset($product, 'video', "products/{$product->uuid}/videos/demo.mp4", 'video/mp4');

    $response = $this->getJson('/api/v1/catalog/products/'.$product->uuid)->assertOk();

    expect($response->json('data.videos'))->toHaveCount(1);
    expect($response->json('data.videos.0.url'))->toBe(
        "https://cdn.htashop.com/products/{$product->uuid}/videos/demo.mp4"
    );
    expect($response->json('data.videos.0.mime_type'))->toBe('video/mp4');

    // Videos must not appear in the image slots.
    expect($response->json('data.gallery'))->toHaveCount(2);
    expect($response->json('data.image_url'))->toContain('hero.jpg');
});

it('returns only videos for a product with no images', function () {
    $product = videoTestProduct();

    damAsset($product, 'video', "products/{$product->uuid}/videos/demo.mp4", 'video/mp4');

    $response = $this->getJson('/api/v1/catalog/products/'.$product->uuid)->assertOk();

    expect($response->json('data.videos'))->toHaveCount(1);
    expect($response->json('data.gallery'))->toBe([]);
    expect($response->json('data.image_url'))->toBeNull();
});

it('exposes videos on the management product resource', function () {
    $product = videoTestProduct();

    damAsset($product, 'video', "products/{$product->uuid}/videos/demo.mp4", 'video/mp4');

    $resource = (new ProductResource($product->load(['dams', 'videos'])))->resolve();

    expect($resource['videos'])->toHaveCount(1);
    expect($resource['videos'][0]['url'])->toContain('videos/demo.mp4');
    expect($resource['image_url'])->toBeNull();
});

it('keeps the featured image as the poster when a video exists', function () {
    $product = videoTestProduct();

    damAsset($product, 'featured', "products/{$product->uuid}/images/hero.jpg", 'image/jpeg');
    damAsset($product, 'video', "products/{$product->uuid}/videos/demo.mp4", 'video/mp4');

    $resource = (new ProductResource($product->load(['dams', 'videos'])))->resolve();

    expect($resource['image_url'])->toContain('hero.jpg');
    expect($resource['videos'])->toHaveCount(1);
});

it('prefers the resized poster variant for video thumbnails', function () {
    $product = videoTestProduct();

    damAsset($product, 'video', "products/{$product->uuid}/videos/demo.mp4", 'video/mp4')->update([
        'metadata' => [
            'poster_key' => "products/{$product->uuid}/videos/posters/poster.jpg",
            'poster_variants' => [
                'medium' => "products/{$product->uuid}/videos/posters/poster-medium.jpg",
            ],
        ],
    ]);

    $response = $this->getJson('/api/v1/catalog/products/'.$product->uuid)->assertOk();

    expect($response->json('data.videos.0.poster_url'))->toBe(
        "https://cdn.htashop.com/products/{$product->uuid}/videos/posters/poster-medium.jpg"
    );
});

it('falls back to the original poster key when no variant exists', function () {
    $product = videoTestProduct();

    damAsset($product, 'video', "products/{$product->uuid}/videos/demo.mp4", 'video/mp4')->update([
        'metadata' => ['poster_key' => "products/{$product->uuid}/videos/posters/poster.jpg"],
    ]);

    $response = $this->getJson('/api/v1/catalog/products/'.$product->uuid)->assertOk();

    expect($response->json('data.videos.0.poster_url'))->toBe(
        "https://cdn.htashop.com/products/{$product->uuid}/videos/posters/poster.jpg"
    );
});
