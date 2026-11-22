<?php

namespace Tests\Feature\Punchout;

use App\Actions\Punchout\IssueHandoffCodeAction;
use App\Models\BuyerItemMapping;
use App\Services\Punchout\BuyerItemMappingService;

function buyerPartNumberCartToken($test, $session): string
{
    $code = app(IssueHandoffCodeAction::class)->handle($session);

    return (string) $test->postJson('/api/v1/punchout/sessions/exchange', ['code' => $code])
        ->json('data.session.access_token');
}

function buyerPartNumberCartSetup(string $slug, array $buyerSettings = []): array
{
    $tenant = punchoutTenant($slug);
    $organization = punchoutOrganization($tenant, $slug.'-org');
    $product = punchoutProduct($tenant, $organization, 'Buyer PN Product '.$slug, ['punchout'], ['price' => 10.00]);
    $buyer = punchoutBuyer($tenant, ['settings' => $buyerSettings]);
    $session = punchoutSession($tenant, 'cxml', 'https://buyer.example/return', $buyer);

    return [$tenant, $organization, $product, $buyer, $session];
}

function buyerPartNumberDecodedCart($test, string $tenantUuid, string $token): string
{
    $response = $test->withHeader('Authorization', 'Punchout '.$token)
        ->post('/api/v1/punchout/'.$tenantUuid.'/cart/return', []);

    $response->assertOk();

    preg_match('/name="cXML-urlencoded" value="([^"]*)"/', $response->getContent(), $matches);

    return html_entity_decode($matches[1] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

it('snapshots the buyer part number on the cart line and returns it in cxml', function () {
    [$tenant, , $product, $buyer, $session] = buyerPartNumberCartSetup('bpn-cart-tenant');

    app(BuyerItemMappingService::class)->map($buyer, [
        'buyer_part_number' => '1000048217',
        'product_id' => $product->id,
    ]);

    $token = buyerPartNumberCartToken($this, $session);

    $this->withHeader('Authorization', 'Punchout '.$token)
        ->postJson('/api/v1/punchout/'.$tenant->uuid.'/cart/items', [
            'product_id' => $product->id,
            'quantity' => 2,
        ])
        ->assertCreated()
        ->assertJsonPath('data.items.0.buyer_part_number', '1000048217')
        ->assertJsonPath('data.items.0.customer_part_number', '1000048217');

    $xml = buyerPartNumberDecodedCart($this, $tenant->uuid, $token);

    expect($xml)->toContain('<BuyerPartID>1000048217</BuyerPartID>');
});

it('keeps the snapshotted code after the mapping is removed', function () {
    [$tenant, , $product, $buyer, $session] = buyerPartNumberCartSetup('bpn-snapshot-tenant');

    app(BuyerItemMappingService::class)->map($buyer, [
        'buyer_part_number' => 'SNAP-1',
        'product_id' => $product->id,
    ]);

    $token = buyerPartNumberCartToken($this, $session);

    $this->withHeader('Authorization', 'Punchout '.$token)
        ->postJson('/api/v1/punchout/'.$tenant->uuid.'/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])
        ->assertCreated();

    BuyerItemMapping::query()->delete();

    $xml = buyerPartNumberDecodedCart($this, $tenant->uuid, $token);

    expect($xml)->toContain('<BuyerPartID>SNAP-1</BuyerPartID>');
});

it('falls back to the legacy price customer part number', function () {
    [$tenant, $organization, $product, , $session] = buyerPartNumberCartSetup('bpn-legacy-tenant');

    punchoutPrice($tenant, $organization, $product, [
        'base_price' => 10.00,
        'metadata' => ['customer_part_number' => 'LEGACY-9'],
    ]);

    $token = buyerPartNumberCartToken($this, $session);

    $this->withHeader('Authorization', 'Punchout '.$token)
        ->postJson('/api/v1/punchout/'.$tenant->uuid.'/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])
        ->assertCreated()
        ->assertJsonPath('data.items.0.buyer_part_number', 'LEGACY-9');

    $xml = buyerPartNumberDecodedCart($this, $tenant->uuid, $token);

    expect($xml)->toContain('<BuyerPartID>LEGACY-9</BuyerPartID>');
});

it('rejects a cart return when the buyer requires a part number and none is mapped', function () {
    [$tenant, , $product, , $session] = buyerPartNumberCartSetup('bpn-required-tenant', [
        'require_buyer_part_number' => true,
    ]);

    $token = buyerPartNumberCartToken($this, $session);

    $this->withHeader('Authorization', 'Punchout '.$token)
        ->postJson('/api/v1/punchout/'.$tenant->uuid.'/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])
        ->assertCreated();

    $this->withHeader('Authorization', 'Punchout '.$token)
        ->post('/api/v1/punchout/'.$tenant->uuid.'/cart/return', [])
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

it('allows the cart return once the required code is mapped', function () {
    [$tenant, , $product, $buyer, $session] = buyerPartNumberCartSetup('bpn-required-mapped', [
        'require_buyer_part_number' => true,
    ]);

    app(BuyerItemMappingService::class)->map($buyer, [
        'buyer_part_number' => 'REQ-1',
        'product_id' => $product->id,
    ]);

    $token = buyerPartNumberCartToken($this, $session);

    $this->withHeader('Authorization', 'Punchout '.$token)
        ->postJson('/api/v1/punchout/'.$tenant->uuid.'/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])
        ->assertCreated();

    $xml = buyerPartNumberDecodedCart($this, $tenant->uuid, $token);

    expect($xml)->toContain('<BuyerPartID>REQ-1</BuyerPartID>');
});

it('emits the buyer part number in an oci cart', function () {
    [$tenant, , $product, $buyer] = buyerPartNumberCartSetup('bpn-oci-tenant');

    $tenant->update(['settings' => ['punchout' => [
        'default_protocol' => 'oci',
        'supported_protocols' => ['oci', 'cxml'],
    ]]]);

    app(BuyerItemMappingService::class)->map($buyer, [
        'buyer_part_number' => 'OCI-1000048217',
        'product_id' => $product->id,
    ]);

    $session = punchoutSession($tenant->refresh(), 'oci', 'https://buyer.example/oci/return', $buyer);
    $token = buyerPartNumberCartToken($this, $session);

    $this->withHeader('Authorization', 'Punchout '.$token)
        ->postJson('/api/v1/punchout/'.$tenant->uuid.'/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])
        ->assertCreated();

    $response = $this->withHeader('Authorization', 'Punchout '.$token)
        ->post('/api/v1/punchout/'.$tenant->uuid.'/cart/return', []);

    $response->assertOk();
    $response->assertSee('NEW_ITEM-EXT_PRODUCT_ID[1]', false);
    $response->assertSee('OCI-1000048217', false);
});
