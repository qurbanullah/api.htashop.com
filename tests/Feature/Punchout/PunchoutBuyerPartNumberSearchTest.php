<?php

namespace Tests\Feature\Punchout;

use App\Actions\Punchout\MatchPunchoutCustomerPartNumbersAction;
use App\Services\Punchout\BuyerItemMappingService;
use App\Services\Search\ProductSearchService;
use Mockery;
use Mockery\MockInterface;

it('matches a buyer item code case-insensitively by prefix', function () {
    $tenant = punchoutTenant('bpn-search-tenant');
    $organization = punchoutOrganization($tenant, 'bpn-search-org');
    $product = punchoutProduct($tenant, $organization, 'Indexed Part Product', ['punchout']);
    $buyer = punchoutBuyer($tenant);

    app(BuyerItemMappingService::class)->map($buyer, [
        'buyer_part_number' => 'AbC-1000048217',
        'product_id' => $product->id,
    ]);

    $action = app(MatchPunchoutCustomerPartNumbersAction::class);

    expect($action->handle($buyer, $tenant->id, 'abc-1000'))->toBe([$product->id]);
    expect($action->handle($buyer, $tenant->id, 'ABC-1000048217'))->toBe([$product->id]);
});

it('ranks an exact code match ahead of a longer prefix match', function () {
    $tenant = punchoutTenant('bpn-search-exact-tenant');
    $organization = punchoutOrganization($tenant, 'bpn-search-exact-org');
    $exact = punchoutProduct($tenant, $organization, 'Exact Code Product', ['punchout']);
    $longer = punchoutProduct($tenant, $organization, 'Longer Code Product', ['punchout']);
    $buyer = punchoutBuyer($tenant);

    $mapper = app(BuyerItemMappingService::class);
    $mapper->map($buyer, ['buyer_part_number' => '1000048217', 'product_id' => $exact->id]);
    $mapper->map($buyer, ['buyer_part_number' => '10000482170', 'product_id' => $longer->id]);

    $ids = app(MatchPunchoutCustomerPartNumbersAction::class)->handle($buyer, $tenant->id, '1000048217');

    expect($ids)->toBe([$exact->id, $longer->id]);
});

it('does not match another buyer item master', function () {
    $tenant = punchoutTenant('bpn-search-isolation-tenant');
    $organization = punchoutOrganization($tenant, 'bpn-search-isolation-org');
    $product = punchoutProduct($tenant, $organization, 'Isolated Part Product', ['punchout']);

    $owner = punchoutBuyer($tenant, ['settings' => ['network_id' => 'AN-SEARCH-OWNER']]);
    $stranger = punchoutBuyer($tenant, ['settings' => ['network_id' => 'AN-SEARCH-STRANGER']]);

    app(BuyerItemMappingService::class)->map($owner, [
        'buyer_part_number' => 'SECRET-12345',
        'product_id' => $product->id,
    ]);

    $action = app(MatchPunchoutCustomerPartNumbersAction::class);

    expect($action->handle($owner, $tenant->id, 'SECRET-12345'))->toBe([$product->id]);
    expect($action->handle($stranger, $tenant->id, 'SECRET-12345'))->toBe([]);
});

it('still falls back to legacy price metadata when no buyer item is mapped', function () {
    $tenant = punchoutTenant('bpn-search-legacy-tenant');
    $organization = punchoutOrganization($tenant, 'bpn-search-legacy-org');
    $product = punchoutProduct($tenant, $organization, 'Legacy Search Product', ['punchout']);
    $buyer = punchoutBuyer($tenant);

    punchoutPrice($tenant, $organization, $product, [
        'base_price' => 10.00,
        'metadata' => ['customer_part_number' => 'LEGACY-7788'],
    ]);

    $ids = app(MatchPunchoutCustomerPartNumbersAction::class)->handle($buyer, $tenant->id, 'LEGACY-7788');

    expect($ids)->toBe([$product->id]);
});

it('ranks the mapped product ahead of typesense relevance hits', function () {
    $tenant = punchoutTenant('bpn-search-endpoint-tenant');
    $organization = punchoutOrganization($tenant, 'bpn-search-endpoint-org');
    $mapped = punchoutProduct($tenant, $organization, 'Mapped Buyer Item', ['punchout']);
    $other = punchoutProduct($tenant, $organization, 'Relevance Only Product', ['punchout']);
    $buyer = punchoutBuyer($tenant);

    app(BuyerItemMappingService::class)->map($buyer, [
        'buyer_part_number' => '1000048217',
        'product_id' => $mapped->id,
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
        ->getJson('/api/v1/punchout/'.$tenant->uuid.'/catalog/products?search=1000048217')
        ->assertOk();

    expect(collect($response->json('data.data'))->pluck('name')->all())
        ->toBe(['Mapped Buyer Item', 'Relevance Only Product']);
});
