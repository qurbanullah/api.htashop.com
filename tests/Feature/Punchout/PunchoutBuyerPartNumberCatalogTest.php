<?php

namespace Tests\Feature\Punchout;

use App\Services\Punchout\BuyerItemMappingService;

it('exposes the buyer part number on catalog cards and the detail page', function () {
    $tenant = punchoutTenant('bpn-catalog-tenant');
    $organization = punchoutOrganization($tenant, 'bpn-catalog-org');
    $product = punchoutProduct($tenant, $organization, 'Catalog Badge Product', ['punchout'], ['price' => 12.50]);
    $buyer = punchoutBuyer($tenant);

    app(BuyerItemMappingService::class)->map($buyer, [
        'buyer_part_number' => '1000048217',
        'product_id' => $product->id,
    ]);

    $token = punchoutAccessToken($this, punchoutSession($tenant, 'cxml', 'https://buyer.example/return', $buyer));

    $list = $this->withHeader('Authorization', 'Punchout '.$token)
        ->getJson('/api/v1/punchout/'.$tenant->uuid.'/catalog/products')
        ->assertOk();

    expect($list->json('data.data.0.buyer_part_number'))->toBe('1000048217');
    expect($list->json('data.data.0.customer_part_number'))->toBe('1000048217');

    $key = $product->slug.'-'.substr((string) $product->uuid, 0, 8);

    $detail = $this->withHeader('Authorization', 'Punchout '.$token)
        ->getJson('/api/v1/punchout/'.$tenant->uuid.'/catalog/products/'.$key)
        ->assertOk();

    expect($detail->json('data.buyer_part_number'))->toBe('1000048217');
});

it('omits the field when the buyer has no mapping', function () {
    $tenant = punchoutTenant('bpn-catalog-empty');
    $organization = punchoutOrganization($tenant, 'bpn-catalog-empty-org');
    punchoutProduct($tenant, $organization, 'No Badge Product', ['punchout'], ['price' => 12.50]);
    $buyer = punchoutBuyer($tenant);

    $token = punchoutAccessToken($this, punchoutSession($tenant, 'cxml', 'https://buyer.example/return', $buyer));

    $list = $this->withHeader('Authorization', 'Punchout '.$token)
        ->getJson('/api/v1/punchout/'.$tenant->uuid.'/catalog/products')
        ->assertOk();

    expect($list->json('data.data.0.buyer_part_number'))->toBeNull();
});
