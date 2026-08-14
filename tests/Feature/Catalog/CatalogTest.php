<?php

use App\Models\Catalog;
use App\Models\Tenant;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;

it('can be created using a factory and automatically generates uuid', function () {
    $catalog = Catalog::factory()->create([
        'name' => 'Spring 2026 Catalog',
    ]);

    expect($catalog)->toBeInstanceOf(Catalog::class);
    expect($catalog->name)->toBe('Spring 2026 Catalog');
    expect($catalog->uuid)->not->toBeNull();
    expect(Str::isUuid($catalog->uuid))->toBeTrue();
});

it('belongs to a tenant', function () {
    $tenant = Tenant::create([
        'name' => 'Acme Tenant',
        'slug' => 'acme-tenant',
        'is_active' => true,
    ]);

    $catalog = Catalog::factory()->create([
        'tenant_id' => $tenant->id,
    ]);

    expect($catalog->tenant)->toBeInstanceOf(Tenant::class);
    expect($catalog->tenant->id)->toBe($tenant->id);
});

it('belongs to an organization', function () {
    $tenant = Tenant::create([
        'name' => 'Acme Tenant',
        'slug' => 'acme-tenant',
        'is_active' => true,
    ]);

    $org = Organization::create([
        'tenant_id' => $tenant->id,
        'name' => 'Vendor Org',
        'slug' => 'vendor-org',
        'type' => 'vendor',
        'is_active' => true,
    ]);

    $catalog = Catalog::factory()->create([
        'tenant_id' => $tenant->id,
        'organization_id' => $org->id,
    ]);

    expect($catalog->organization)->toBeInstanceOf(Organization::class);
    expect($catalog->organization->id)->toBe($org->id);
});

it('can have multiple contracts', function () {
    $catalog = Catalog::factory()->create();

    $buyer = Organization::create([
        'tenant_id' => $catalog->tenant_id,
        'name' => 'Buyer Org',
        'slug' => 'buyer-org',
        'type' => 'buyer',
        'is_active' => true,
    ]);
    
    $contract1 = $catalog->contracts()->create([
        'tenant_id' => $catalog->tenant_id,
        'vendor_id' => $catalog->organization_id,
        'buyer_id' => $buyer->id,
        'contract_number' => 'CON-1',
        'name' => 'First Contract',
        'is_active' => true,
    ]);

    $contract2 = $catalog->contracts()->create([
        'tenant_id' => $catalog->tenant_id,
        'vendor_id' => $catalog->organization_id,
        'buyer_id' => $buyer->id,
        'contract_number' => 'CON-2',
        'name' => 'Second Contract',
        'is_active' => true,
    ]);

    expect($catalog->contracts)->toHaveCount(2);
});

it('can have multiple prices', function () {
    $catalog = Catalog::factory()->create();

    $currency = App\Models\Currency::create([
        'code' => 'USD',
        'name' => 'US Dollar',
        'symbol' => '$',
    ]);

    $product = App\Models\Product::create([
        'tenant_id' => $catalog->tenant_id,
        'organization_id' => $catalog->organization_id,
        'name' => 'Test Product',
        'slug' => 'test-product',
        'status' => 'active',
        'is_active' => true,
    ]);
    
    $price1 = $catalog->prices()->create([
        'tenant_id' => $catalog->tenant_id,
        'organization_id' => $catalog->organization_id,
        'currency_id' => $currency->id,
        'priceable_type' => App\Models\Product::class,
        'priceable_id' => $product->id,
        'amount' => '99.99',
    ]);

    $price2 = $catalog->prices()->create([
        'tenant_id' => $catalog->tenant_id,
        'organization_id' => $catalog->organization_id,
        'currency_id' => $currency->id,
        'priceable_type' => App\Models\Product::class,
        'priceable_id' => $product->id,
        'amount' => '19.99',
    ]);

    expect($catalog->prices)->toHaveCount(2);
});
