<?php

use App\Actions\Product\SyncProductSalesChannelsAction;
use App\Jobs\Search\SyncProductToSearchJob;
use App\Models\Contract;
use App\Models\Organization;
use App\Models\Tenant;
use App\Services\Search\ProductSearchService;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;
use Typesense\Exceptions\TypesenseClientError;

function punchoutSearchBuyerWithContract(
    Tenant $tenant,
    string $code,
    Organization $buyerOrganization,
    Organization $vendor,
): Contract {
    return Contract::query()->create([
        'tenant_id' => $tenant->id,
        'vendor_id' => $vendor->id,
        'buyer_id' => $buyerOrganization->id,
        'currency_id' => punchoutCurrency()->id,
        'name' => 'Contract '.$code,
        'code' => $code,
        'contract_type' => 'blanket_purchase_agreement',
        'status' => 'active',
    ]);
}

function punchoutSearchToken($test, $tenant): string
{
    return punchoutAccessToken($test, punchoutSession($tenant));
}

it('uses typesense for a typed punchout query and keeps its relevance order', function () {
    $tenant = punchoutTenant('search-tenant');
    $organization = punchoutOrganization($tenant, 'search-org');
    $drill = punchoutProduct($tenant, $organization, 'Cordless Drill', ['punchout']);
    $driver = punchoutProduct($tenant, $organization, 'Impact Driver', ['punchout']);

    $token = punchoutSearchToken($this, $tenant);

    $this->instance(ProductSearchService::class, Mockery::mock(ProductSearchService::class, function (MockInterface $mock) use ($tenant, $drill, $driver) {
        $mock->shouldReceive('isEnabled')->andReturn(true);
        $mock->shouldReceive('search')
            ->once()
            ->with(Mockery::on(fn (array $params): bool => $params['query'] === 'dril'
                && $params['filters']['channel'] === 'punchout'
                && $params['filters']['tenant_id'] === $tenant->id
                && $params['query_by_weights'] === '4,5,6,4,1,1,1,2,1'))
            ->andReturn([
                // Deliberately not the database order: the index decides ranking.
                'hits' => [
                    ['id' => (string) $driver->id],
                    ['id' => (string) $drill->id],
                ],
                'facets' => [],
                'found' => 2,
                'page' => 1,
                'per_page' => 24,
                'search_time_ms' => 2,
            ]);
    }));

    $response = $this->withHeader('Authorization', 'Punchout '.$token)
        ->getJson('/api/v1/punchout/'.$tenant->uuid.'/catalog/products?search=dril')
        ->assertOk()
        ->assertJsonPath('data.meta.total', 2)
        ->assertJsonPath('data.data.0.name', 'Impact Driver')
        ->assertJsonPath('data.data.1.name', 'Cordless Drill');
});

it('drops stale index hits that are no longer in the punchout channel', function () {
    $tenant = punchoutTenant('search-stale-tenant');
    $organization = punchoutOrganization($tenant, 'search-stale-org');
    $visible = punchoutProduct($tenant, $organization, 'Visible Punchout Product', ['punchout']);
    $leftChannel = punchoutProduct($tenant, $organization, 'Left The Channel', ['storefront']);

    $token = punchoutSearchToken($this, $tenant);

    $this->instance(ProductSearchService::class, Mockery::mock(ProductSearchService::class, function (MockInterface $mock) use ($leftChannel, $visible) {
        $mock->shouldReceive('isEnabled')->andReturn(true);
        $mock->shouldReceive('search')->once()->andReturn([
            'hits' => [
                ['id' => (string) $leftChannel->id],
                ['id' => (string) $visible->id],
            ],
            'facets' => [],
            'found' => 2,
            'page' => 1,
            'per_page' => 24,
            'search_time_ms' => 1,
        ]);
    }));

    $response = $this->withHeader('Authorization', 'Punchout '.$token)
        ->getJson('/api/v1/punchout/'.$tenant->uuid.'/catalog/products?search=punchout')
        ->assertOk();

    expect(collect($response->json('data.data'))->pluck('name')->all())
        ->toBe(['Visible Punchout Product']);
});

it('falls back to the database when typesense fails', function () {
    $tenant = punchoutTenant('search-fallback-tenant');
    $organization = punchoutOrganization($tenant, 'search-fallback-org');
    punchoutProduct($tenant, $organization, 'Fallback Impact Driver', ['punchout']);
    punchoutProduct($tenant, $organization, 'Unrelated Product', ['punchout']);

    $token = punchoutSearchToken($this, $tenant);

    $this->instance(ProductSearchService::class, Mockery::mock(ProductSearchService::class, function (MockInterface $mock) {
        $mock->shouldReceive('isEnabled')->andReturn(true);
        $mock->shouldReceive('search')->once()->andThrow(new TypesenseClientError('typesense is down'));
    }));

    $response = $this->withHeader('Authorization', 'Punchout '.$token)
        ->getJson('/api/v1/punchout/'.$tenant->uuid.'/catalog/products?search=Impact')
        ->assertOk();

    expect(collect($response->json('data.data'))->pluck('name')->all())
        ->toContain('Fallback Impact Driver')
        ->not->toContain('Unrelated Product');
});

it('does not query typesense when browsing without a search term', function () {
    $tenant = punchoutTenant('search-browse-tenant');
    $organization = punchoutOrganization($tenant, 'search-browse-org');
    punchoutProduct($tenant, $organization, 'Browsable Product', ['punchout']);

    $token = punchoutSearchToken($this, $tenant);

    $this->instance(ProductSearchService::class, Mockery::mock(ProductSearchService::class, function (MockInterface $mock) {
        $mock->shouldReceive('isEnabled')->andReturn(true);
        $mock->shouldReceive('search')->never();
    }));

    $this->withHeader('Authorization', 'Punchout '.$token)
        ->getJson('/api/v1/punchout/'.$tenant->uuid.'/catalog/products')
        ->assertOk()
        ->assertJsonPath('data.meta.total', 1)
        ->assertJsonPath('data.data.0.name', 'Browsable Product');
});

it('reindexes a product when its channel membership changes', function () {
    Queue::fake();
    config()->set('typesense.enabled', true);
    config()->set('typesense.api_key', 'test-key');

    $tenant = punchoutTenant('channel-reindex-tenant');
    $organization = punchoutOrganization($tenant, 'channel-reindex-org');
    $product = punchoutProduct($tenant, $organization, 'Channel Reindex Product', ['storefront']);

    app(SyncProductSalesChannelsAction::class)
        ->handle($product, ['storefront', 'punchout']);

    Queue::assertPushed(
        SyncProductToSearchJob::class,
        fn (SyncProductToSearchJob $job): bool => $job->productId === $product->id && $job->action === 'upsert'
    );
});

it('ranks a buyer part-number match ahead of relevance hits', function () {
    $tenant = punchoutTenant('part-number-tenant');
    $vendor = punchoutOrganization($tenant, 'part-number-vendor');
    $matched = punchoutProduct($tenant, $vendor, 'Buyer Part Product', ['punchout']);
    $other = punchoutProduct($tenant, $vendor, 'Other Product', ['punchout']);

    $buyerOrganization = punchoutBuyerOrganization($tenant, 'part-number-buyer-org');
    $contract = punchoutSearchBuyerWithContract($tenant, 'pn-contract', $buyerOrganization, $vendor);

    punchoutPrice($tenant, $vendor, $matched, [
        'contract_id' => $contract->id,
        'base_price' => 12.00,
        'metadata' => ['customer_part_number' => 'ACME-7788'],
    ]);

    $buyer = punchoutBuyer($tenant, [
        'code' => 'part-number-buyer',
        'organization_id' => $buyerOrganization->id,
        'contract_id' => $contract->id,
    ]);

    $token = punchoutAccessToken($this, punchoutSession($tenant, 'cxml', 'https://buyer.example/return', $buyer));

    $this->instance(ProductSearchService::class, Mockery::mock(ProductSearchService::class, function (MockInterface $mock) use ($other) {
        $mock->shouldReceive('isEnabled')->andReturn(true);
        $mock->shouldReceive('search')->once()->andReturn([
            'hits' => [['id' => (string) $other->id]],
            'facets' => [],
            'found' => 1,
            'page' => 1,
            'per_page' => 24,
            'search_time_ms' => 1,
        ]);
    }));

    $response = $this->withHeader('Authorization', 'Punchout '.$token)
        ->getJson('/api/v1/punchout/'.$tenant->uuid.'/catalog/products?search=ACME-7788')
        ->assertOk();

    expect(collect($response->json('data.data'))->pluck('name')->all())
        ->toBe(['Buyer Part Product', 'Other Product']);
});

it('does not match another buyer contract part numbers', function () {
    $tenant = punchoutTenant('part-number-isolation-tenant');
    $vendor = punchoutOrganization($tenant, 'part-number-isolation-vendor');
    $product = punchoutProduct($tenant, $vendor, 'Secret Part Product', ['punchout']);

    $buyerOrganizationA = punchoutBuyerOrganization($tenant, 'pn-isolation-buyer-a');
    $buyerOrganizationB = punchoutBuyerOrganization($tenant, 'pn-isolation-buyer-b');
    $contractB = punchoutSearchBuyerWithContract($tenant, 'pn-isolation-contract-b', $buyerOrganizationB, $vendor);

    punchoutPrice($tenant, $vendor, $product, [
        'contract_id' => $contractB->id,
        'metadata' => ['customer_part_number' => 'SECRET-9999'],
    ]);

    $buyerA = punchoutBuyer($tenant, [
        'code' => 'pn-isolation-buyer-a',
        'organization_id' => $buyerOrganizationA->id,
    ]);

    $token = punchoutAccessToken($this, punchoutSession($tenant, 'cxml', 'https://buyer.example/return', $buyerA));

    $this->instance(ProductSearchService::class, Mockery::mock(ProductSearchService::class, function (MockInterface $mock) {
        $mock->shouldReceive('isEnabled')->andReturn(true);
        $mock->shouldReceive('search')->once()->andReturn([
            'hits' => [],
            'facets' => [],
            'found' => 0,
            'page' => 1,
            'per_page' => 24,
            'search_time_ms' => 1,
        ]);
    }));

    $this->withHeader('Authorization', 'Punchout '.$token)
        ->getJson('/api/v1/punchout/'.$tenant->uuid.'/catalog/products?search=SECRET-9999')
        ->assertOk()
        ->assertJsonPath('data.meta.total', 0)
        ->assertJsonCount(0, 'data.data');
});

it('includes buyer part numbers in the database fallback', function () {
    $tenant = punchoutTenant('part-number-fallback-tenant');
    $vendor = punchoutOrganization($tenant, 'part-number-fallback-vendor');
    $product = punchoutProduct($tenant, $vendor, 'Fallback Part Product', ['punchout']);

    $buyerOrganization = punchoutBuyerOrganization($tenant, 'pn-fallback-buyer-org');
    $contract = punchoutSearchBuyerWithContract($tenant, 'pn-fallback-contract', $buyerOrganization, $vendor);

    punchoutPrice($tenant, $vendor, $product, [
        'contract_id' => $contract->id,
        'metadata' => ['customer_part_number' => 'ACME-4321'],
    ]);

    $buyer = punchoutBuyer($tenant, [
        'code' => 'pn-fallback-buyer',
        'organization_id' => $buyerOrganization->id,
        'contract_id' => $contract->id,
    ]);

    $token = punchoutAccessToken($this, punchoutSession($tenant, 'cxml', 'https://buyer.example/return', $buyer));

    $this->instance(ProductSearchService::class, Mockery::mock(ProductSearchService::class, function (MockInterface $mock) {
        $mock->shouldReceive('isEnabled')->andReturn(true);
        $mock->shouldReceive('search')->once()->andThrow(new TypesenseClientError('typesense is down'));
    }));

    $response = $this->withHeader('Authorization', 'Punchout '.$token)
        ->getJson('/api/v1/punchout/'.$tenant->uuid.'/catalog/products?search=ACME-4321')
        ->assertOk();

    expect(collect($response->json('data.data'))->pluck('name')->all())
        ->toContain('Fallback Part Product');
});
