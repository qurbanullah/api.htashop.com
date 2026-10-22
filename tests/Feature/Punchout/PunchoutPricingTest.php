<?php

use App\Models\Contract;
use App\Models\Organization;
use App\Models\Product;
use App\Models\PunchoutBuyer;
use App\Models\Tenant;

function punchoutBuyerWithContract(
    Tenant $tenant,
    string $code,
    Organization $buyerOrganization,
    Organization $vendor,
): Contract {
    $currency = punchoutCurrency();

    return Contract::query()->create([
        'tenant_id' => $tenant->id,
        'vendor_id' => $vendor->id,
        'buyer_id' => $buyerOrganization->id,
        'currency_id' => $currency->id,
        'name' => 'Contract '.$code,
        'code' => $code,
        'contract_type' => 'blanket_purchase_agreement',
        'status' => 'active',
    ]);
}

it('shows different contract prices to two buyers on the same tenant', function () {
    $tenant = punchoutTenant('pricing-two-buyers');
    $vendor = punchoutOrganization($tenant, 'pricing-vendor');
    $product = punchoutProduct($tenant, $vendor, 'Contract Widget', ['punchout'], ['price' => 10.00]);

    $buyerOrgA = punchoutBuyerOrganization($tenant, 'buyer-a');
    $buyerOrgB = punchoutBuyerOrganization($tenant, 'buyer-b');

    $contractA = punchoutBuyerWithContract($tenant, 'contract-a', $buyerOrgA, $vendor);
    $contractB = punchoutBuyerWithContract($tenant, 'contract-b', $buyerOrgB, $vendor);

    punchoutPrice($tenant, $vendor, $product, ['contract_id' => $contractA->id, 'base_price' => 7.50]);
    punchoutPrice($tenant, $vendor, $product, ['contract_id' => $contractB->id, 'base_price' => 9.25]);

    $buyerA = punchoutBuyer($tenant, [
        'code' => 'buyer-a',
        'organization_id' => $buyerOrgA->id,
        'contract_id' => $contractA->id,
        'from_identity' => 'from-a',
    ]);
    $buyerB = punchoutBuyer($tenant, [
        'code' => 'buyer-b',
        'organization_id' => $buyerOrgB->id,
        'contract_id' => $contractB->id,
        'from_identity' => 'from-b',
    ]);

    expect(punchoutCartUnitPrice($this, $tenant, $buyerA, $product, 1))->toBe(7.50);
    expect(punchoutCartUnitPrice($this, $tenant, $buyerB, $product, 1))->toBe(9.25);
});

it('never leaks another buyer contract price', function () {
    $tenant = punchoutTenant('pricing-no-leak');
    $vendor = punchoutOrganization($tenant, 'no-leak-vendor');
    $product = punchoutProduct($tenant, $vendor, 'No Leak Widget', ['punchout'], ['price' => 10.00]);

    $buyerOrgA = punchoutBuyerOrganization($tenant, 'no-leak-buyer-a');
    $buyerOrgB = punchoutBuyerOrganization($tenant, 'no-leak-buyer-b');

    $contractB = punchoutBuyerWithContract($tenant, 'contract-b', $buyerOrgB, $vendor);
    punchoutPrice($tenant, $vendor, $product, ['contract_id' => $contractB->id, 'base_price' => 3.00]);

    $buyerA = punchoutBuyer($tenant, [
        'code' => 'no-leak-buyer-a',
        'organization_id' => $buyerOrgA->id,
    ]);

    expect(punchoutCartUnitPrice($this, $tenant, $buyerA, $product, 1))->toBe(10.00);
});

it('applies volume tiers to the resulting line quantity', function () {
    $tenant = punchoutTenant('pricing-tiers');
    $vendor = punchoutOrganization($tenant, 'tier-vendor');
    $product = punchoutProduct($tenant, $vendor, 'Tier Widget', ['punchout'], ['price' => 10.00]);
    $buyer = punchoutBuyer($tenant, ['code' => 'tier-buyer']);

    punchoutPrice($tenant, $vendor, $product, ['base_price' => 8.00, 'min_quantity' => 1]);
    punchoutPrice($tenant, $vendor, $product, ['base_price' => 6.00, 'min_quantity' => 10]);

    expect(punchoutCartUnitPrice($this, $tenant, $buyer, $product, 2))->toBe(8.00);

    $session = punchoutSession($tenant, 'cxml', 'https://buyer.example/return', $buyer);
    $token = punchoutAccessToken($this, $session);
    $headers = ['Authorization' => 'Punchout '.$token];

    $this->withHeaders($headers)
        ->postJson('/api/v1/punchout/'.$tenant->uuid.'/cart/items', [
            'product_id' => $product->id,
            'quantity' => 2,
        ])
        ->assertCreated();

    $this->withHeaders($headers)
        ->postJson('/api/v1/punchout/'.$tenant->uuid.'/cart/items', [
            'product_id' => $product->id,
            'quantity' => 8,
        ])
        ->assertCreated()
        ->assertJsonPath('data.items.0.quantity', 10);

    expect((float) $this->withHeaders($headers)
        ->getJson('/api/v1/punchout/'.$tenant->uuid.'/cart')
        ->json('data.items.0.unit_price'))->toBe(6.00);
});

it('exposes customer part numbers and units of measure in cart and return', function () {
    $tenant = punchoutTenant('pricing-part-numbers');
    $vendor = punchoutOrganization($tenant, 'part-vendor');
    $product = punchoutProduct($tenant, $vendor, 'Part Widget', ['punchout'], ['price' => 10.00]);
    $buyer = punchoutBuyer($tenant, ['code' => 'part-buyer']);

    punchoutPrice($tenant, $vendor, $product, [
        'base_price' => 5.00,
        'metadata' => [
            'customer_part_number' => 'BUY-123',
            'unit_of_measure' => 'BOX',
        ],
    ]);

    $session = punchoutSession($tenant, 'cxml', 'https://buyer.example/return', $buyer);
    $token = punchoutAccessToken($this, $session);
    $headers = ['Authorization' => 'Punchout '.$token];

    $response = $this->withHeaders($headers)
        ->postJson('/api/v1/punchout/'.$tenant->uuid.'/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])
        ->assertCreated()
        ->assertJsonPath('data.items.0.customer_part_number', 'BUY-123')
        ->assertJsonPath('data.items.0.unit_of_measure', 'BOX')
        ->assertJsonPath('data.items.0.price_source', 'price_list');

    expect((float) $response->json('data.items.0.unit_price'))->toBe(5.00);

    $this->withHeaders($headers)
        ->post('/api/v1/punchout/'.$tenant->uuid.'/cart/return', [])
        ->assertOk()
        ->assertSee('BuyerPartID', false)
        ->assertSee('BUY-123', false)
        ->assertSee('BOX', false);
});

it('exposes the resolved price in the catalog listing', function () {
    $tenant = punchoutTenant('pricing-catalog');
    $vendor = punchoutOrganization($tenant, 'catalog-vendor');
    $product = punchoutProduct($tenant, $vendor, 'Catalog Widget', ['punchout'], ['price' => 10.00]);

    $buyerOrg = punchoutBuyerOrganization($tenant, 'catalog-buyer-org');
    $contract = punchoutBuyerWithContract($tenant, 'catalog-contract', $buyerOrg, $vendor);
    punchoutPrice($tenant, $vendor, $product, ['contract_id' => $contract->id, 'base_price' => 7.50]);

    $buyer = punchoutBuyer($tenant, [
        'code' => 'catalog-buyer',
        'organization_id' => $buyerOrg->id,
        'contract_id' => $contract->id,
    ]);
    $session = punchoutSession($tenant, 'cxml', 'https://buyer.example/return', $buyer);
    $token = punchoutAccessToken($this, $session);

    $response = $this->withHeader('Authorization', 'Punchout '.$token)
        ->getJson('/api/v1/punchout/'.$tenant->uuid.'/catalog/products')
        ->assertOk();

    expect((float) $response->json('data.data.0.unit_price'))->toBe(7.50);
    expect($response->json('data.data.0.price_source'))->toBe('contract');
});

function punchoutCartUnitPrice($test, Tenant $tenant, PunchoutBuyer $buyer, Product $product, int $quantity): float
{
    $session = punchoutSession($tenant, 'cxml', 'https://buyer.example/return', $buyer);
    $token = punchoutAccessToken($test, $session);

    $response = $test->withHeader('Authorization', 'Punchout '.$token)
        ->postJson('/api/v1/punchout/'.$tenant->uuid.'/cart/items', [
            'product_id' => $product->id,
            'quantity' => $quantity,
        ])
        ->assertCreated();

    return (float) $response->json('data.items.0.unit_price');
}
