<?php

use App\Models\Category;
use App\Services\Search\ProductSearchService;
use Mockery\MockInterface;
use Typesense\Exceptions\TypesenseClientError;

it('returns priced typesense suggestions scoped to the session tenant and channel', function () {
    $tenant = punchoutTenant('suggest-tenant');
    $organization = punchoutOrganization($tenant, 'suggest-org');
    $product = punchoutProduct($tenant, $organization, 'Cordless Drill', ['punchout'], ['price' => 42.50]);

    $token = punchoutAccessToken($this, punchoutSession($tenant));

    $this->instance(ProductSearchService::class, Mockery::mock(ProductSearchService::class, function (MockInterface $mock) use ($tenant, $product) {
        $mock->shouldReceive('suggest')
            ->once()
            ->with('dril', 8, null, ['channel' => 'punchout', 'tenant_id' => $tenant->id])
            ->andReturn([[
                'id' => (string) $product->id,
                'uuid' => $product->uuid,
                'name' => $product->name,
                'slug' => $product->slug,
                'route_key' => $product->slug.'-'.substr((string) $product->uuid, 0, 8),
                'price' => 42.5,
                'sale_price' => null,
                'currency' => 'USD',
                'image_url' => null,
                'summary' => null,
                'brands' => [],
                'categories' => [],
            ]]);
    }));

    $response = $this->withHeader('Authorization', 'Punchout '.$token)
        ->getJson('/api/v1/punchout/'.$tenant->uuid.'/catalog/suggest?q=dril')
        ->assertOk()
        ->assertJsonCount(1, 'data.data');

    expect($response->json('data.data.0'))->toMatchArray([
        'id' => $product->id,
        'name' => 'Cordless Drill',
        'route_key' => 'cordless-drill-'.substr((string) $product->uuid, 0, 8),
        'unit_price' => 42.5,
        'currency' => 'USD',
    ]);
});

it('skips typesense for fewer than two characters', function () {
    $tenant = punchoutTenant('suggest-short-tenant');
    $token = punchoutAccessToken($this, punchoutSession($tenant));

    $this->instance(ProductSearchService::class, Mockery::mock(ProductSearchService::class, function (MockInterface $mock) {
        $mock->shouldReceive('suggest')->never();
    }));

    $this->withHeader('Authorization', 'Punchout '.$token)
        ->getJson('/api/v1/punchout/'.$tenant->uuid.'/catalog/suggest?q=d')
        ->assertOk()
        ->assertJsonCount(0, 'data.data');
});

it('falls back to the database when typesense fails', function () {
    $tenant = punchoutTenant('suggest-fallback-tenant');
    $organization = punchoutOrganization($tenant, 'suggest-fallback-org');
    $product = punchoutProduct($tenant, $organization, 'Fallback Drill', ['punchout']);

    $token = punchoutAccessToken($this, punchoutSession($tenant));

    $this->instance(ProductSearchService::class, Mockery::mock(ProductSearchService::class, function (MockInterface $mock) use ($product) {
        $mock->shouldReceive('suggest')->once()->andThrow(new TypesenseClientError('typesense is down'));
        $mock->shouldReceive('fallbackSuggest')->once()->andReturn([[
            'id' => (string) $product->id,
            'uuid' => $product->uuid,
            'name' => $product->name,
            'slug' => $product->slug,
            'route_key' => $product->slug.'-'.substr((string) $product->uuid, 0, 8),
            'image_url' => null,
        ]]);
    }));

    $response = $this->withHeader('Authorization', 'Punchout '.$token)
        ->getJson('/api/v1/punchout/'.$tenant->uuid.'/catalog/suggest?q=drill')
        ->assertOk()
        ->assertJsonCount(1, 'data.data');

    expect($response->json('data.data.0.name'))->toBe('Fallback Drill');
});

it('drops suggestions that belong to another tenant', function () {
    $tenantA = punchoutTenant('suggest-tenant-a');
    $organizationA = punchoutOrganization($tenantA, 'suggest-org-a');
    punchoutProduct($tenantA, $organizationA, 'Tenant A Product', ['punchout']);

    $tenantB = punchoutTenant('suggest-tenant-b');
    $organizationB = punchoutOrganization($tenantB, 'suggest-org-b');
    $foreign = punchoutProduct($tenantB, $organizationB, 'Tenant B Product', ['punchout']);

    $token = punchoutAccessToken($this, punchoutSession($tenantA));

    $this->instance(ProductSearchService::class, Mockery::mock(ProductSearchService::class, function (MockInterface $mock) use ($foreign) {
        $mock->shouldReceive('suggest')->once()->andReturn([[
            'id' => (string) $foreign->id,
            'uuid' => $foreign->uuid,
            'name' => $foreign->name,
            'slug' => $foreign->slug,
            'route_key' => $foreign->slug.'-'.substr((string) $foreign->uuid, 0, 8),
            'image_url' => null,
        ]]);
    }));

    $this->withHeader('Authorization', 'Punchout '.$token)
        ->getJson('/api/v1/punchout/'.$tenantA->uuid.'/catalog/suggest?q=product')
        ->assertOk()
        ->assertJsonCount(0, 'data.data');
});

it('passes the requested category to typesense', function () {
    $tenant = punchoutTenant('suggest-category-tenant');
    $token = punchoutAccessToken($this, punchoutSession($tenant));

    $this->instance(ProductSearchService::class, Mockery::mock(ProductSearchService::class, function (MockInterface $mock) use ($tenant) {
        $mock->shouldReceive('suggest')
            ->once()
            ->with('drill', 8, 42, ['channel' => 'punchout', 'tenant_id' => $tenant->id])
            ->andReturn([]);
    }));

    $this->withHeader('Authorization', 'Punchout '.$token)
        ->getJson('/api/v1/punchout/'.$tenant->uuid.'/catalog/suggest?q=drill&category_id=42')
        ->assertOk()
        ->assertJsonCount(0, 'data.data');
});

it('lists only categories that contain punchout products', function () {
    $tenant = punchoutTenant('filters-category-tenant');
    $organization = punchoutOrganization($tenant, 'filters-category-org');
    $product = punchoutProduct($tenant, $organization, 'Category Filter Product', ['punchout']);

    $used = Category::query()->create(['name' => 'Drills', 'slug' => 'drills', 'level' => 0]);
    Category::query()->create(['name' => 'Empty Category', 'slug' => 'empty-category', 'level' => 0]);
    $used->products()->attach($product->id);

    $token = punchoutAccessToken($this, punchoutSession($tenant));

    $response = $this->withHeader('Authorization', 'Punchout '.$token)
        ->getJson('/api/v1/punchout/'.$tenant->uuid.'/catalog/filters')
        ->assertOk();

    $names = collect($response->json('data.categories'))->pluck('name')->all();

    expect($names)->toContain('Drills')->not->toContain('Empty Category');
});
