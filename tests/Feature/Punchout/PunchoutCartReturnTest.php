<?php

use App\Actions\Punchout\IssueHandoffCodeAction;
use App\Actions\Punchout\ValidatePunchoutReturnUrlAction;
use Illuminate\Validation\ValidationException;

function punchoutCartToken($test, $session): string
{
    $code = app(IssueHandoffCodeAction::class)->handle($session);

    return (string) $test->postJson('/api/v1/punchout/sessions/exchange', ['code' => $code])
        ->json('data.session.access_token');
}

it('adds an item and returns a cxml cart to the setup url', function () {
    $tenant = punchoutTenant('cart-return-tenant');
    $organization = punchoutOrganization($tenant, 'cart-return-org');
    $product = punchoutProduct($tenant, $organization, 'Cart Return Product', ['punchout'], ['price' => 12.50]);
    $session = punchoutSession($tenant);
    $token = punchoutCartToken($this, $session);

    $headers = ['Authorization' => 'Punchout '.$token];

    $this->withHeaders($headers)
        ->postJson('/api/v1/punchout/'.$tenant->uuid.'/cart/items', [
            'product_id' => $product->id,
            'quantity' => 2,
        ])
        ->assertCreated()
        ->assertJsonPath('data.count', 2);

    $response = $this->withHeaders($headers)
        ->post('/api/v1/punchout/'.$tenant->uuid.'/cart/return', []);

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/html');
    $response->assertSee('cXML-urlencoded', false);
    $response->assertSee($product->uuid, false);
    $response->assertSee('https://buyer.example/return', false);

    expect($session->fresh()->status)->toBe('completed');
    expect($session->fresh()->cart_items)->toHaveCount(1);
});

it('updates and removes a cart line by its own uuid', function () {
    $tenant = punchoutTenant('cart-line-tenant');
    $organization = punchoutOrganization($tenant, 'cart-line-org');
    $product = punchoutProduct($tenant, $organization, 'Cart Line Product', ['punchout'], ['price' => 10.00]);
    $session = punchoutSession($tenant);
    $token = punchoutCartToken($this, $session);
    $headers = ['Authorization' => 'Punchout '.$token];

    $itemUuid = (string) $this->withHeaders($headers)
        ->postJson('/api/v1/punchout/'.$tenant->uuid.'/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])
        ->assertCreated()
        ->json('data.items.0.uuid');

    expect($itemUuid)->not->toBe('');

    $this->withHeaders($headers)
        ->patchJson('/api/v1/punchout/'.$tenant->uuid.'/cart/items/'.$itemUuid, ['quantity' => 5])
        ->assertOk()
        ->assertJsonPath('data.items.0.quantity', 5);

    $this->withHeaders($headers)
        ->deleteJson('/api/v1/punchout/'.$tenant->uuid.'/cart/items/'.$itemUuid)
        ->assertOk()
        ->assertJsonPath('data.items', []);
});

it('rejects products outside the tenant punchout channel', function () {
    $tenant = punchoutTenant('cart-scope-tenant');
    $organization = punchoutOrganization($tenant, 'cart-scope-org');
    $storefrontOnly = punchoutProduct($tenant, $organization, 'Cart Storefront Only', ['storefront']);
    $session = punchoutSession($tenant);
    $token = punchoutCartToken($this, $session);

    $this->withHeader('Authorization', 'Punchout '.$token)
        ->postJson('/api/v1/punchout/'.$tenant->uuid.'/cart/items', [
            'product_id' => $storefrontOnly->id,
            'quantity' => 1,
        ])
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

it('rejects returning an empty cart', function () {
    $tenant = punchoutTenant('empty-cart-tenant');
    $session = punchoutSession($tenant);
    $token = punchoutCartToken($this, $session);

    $this->withHeader('Authorization', 'Punchout '.$token)
        ->post('/api/v1/punchout/'.$tenant->uuid.'/cart/return', [])
        ->assertStatus(422);
});

it('rejects a return url that does not match the setup url', function () {
    $tenant = punchoutTenant('return-url-tenant');
    $session = punchoutSession($tenant, 'cxml', 'https://buyer.example/return');

    $action = app(ValidatePunchoutReturnUrlAction::class);

    expect(fn () => $action->handle($session, 'https://evil.example/return'))
        ->toThrow(ValidationException::class);
});
