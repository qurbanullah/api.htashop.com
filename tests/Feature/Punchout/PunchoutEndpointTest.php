<?php

use App\Models\PunchoutSession;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

function createStartedPunchoutSession(Tenant $tenant, string $protocol, ?string $buyerCookie = null, ?string $returnUrl = null): PunchoutSession
{
    return PunchoutSession::create([
        'tenant_id' => $tenant->id,
        'protocol' => $protocol,
        'status' => 'started',
        'buyer_cookie' => $buyerCookie,
        'return_url' => $returnUrl ?? 'https://buyer.example/return',
        'started_at' => now(),
        'last_activity_at' => now(),
    ]);
}

it('returns a cxml setup response for a tenant', function () {
    config()->set('app.url', 'https://supplier.example');

    $tenant = Tenant::create([
        'name' => 'Acme Tenant',
        'slug' => 'acme-tenant',
        'is_active' => true,
        'settings' => [
            'punchout' => [
                'default_protocol' => 'cxml',
                'supported_protocols' => ['cxml', 'oci'],
            ],
        ],
    ]);

    $payload = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<cXML>
  <Request>
    <PunchOutSetupRequest>
      <BuyerCookie>buyer-cookie-123</BuyerCookie>
      <BrowserFormPost>
        <URL>https://buyer.example/punchout/return</URL>
      </BrowserFormPost>
    </PunchOutSetupRequest>
  </Request>
</cXML>
XML;

    $response = $this->call('POST', '/api/v1/punchout/' . $tenant->uuid . '/setup?protocol=cxml', [], [], [], ['CONTENT_TYPE' => 'text/xml'], $payload);

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/xml');
    $response->assertSee('PunchOutSetupResponse', false);
    $response->assertSee('https://supplier.example/api/v1/punchout/' . $tenant->uuid . '/start?session=', false);

    expect(PunchoutSession::query()->count())->toBe(1);
    expect(PunchoutSession::query()->first()?->buyer_cookie)->toBe('buyer-cookie-123');
});

it('rejects a cxml setup request with invalid configured credentials', function () {
        $tenant = Tenant::create([
                'name' => 'Credential Tenant',
                'slug' => 'credential-tenant',
                'is_active' => true,
                'settings' => [
                        'punchout' => [
                                'default_protocol' => 'cxml',
                                'supported_protocols' => ['cxml'],
                                'protocols' => [
                                        'cxml' => [
                                                'credentials' => [
                                                        'sender_identity' => 'expected-sender',
                                                        'shared_secret' => 'expected-secret',
                                                ],
                                        ],
                                ],
                        ],
                ],
        ]);

        $payload = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<cXML>
    <Header>
        <Sender>
            <Credential>
                <Identity>unexpected-sender</Identity>
                <SharedSecret>wrong-secret</SharedSecret>
            </Credential>
        </Sender>
    </Header>
    <Request>
        <PunchOutSetupRequest>
            <BuyerCookie>buyer-cookie-123</BuyerCookie>
            <BrowserFormPost>
                <URL>https://buyer.example/punchout/return</URL>
            </BrowserFormPost>
        </PunchOutSetupRequest>
    </Request>
</cXML>
XML;

        $response = $this->call('POST', '/api/v1/punchout/' . $tenant->uuid . '/setup?protocol=cxml', [], [], [], ['CONTENT_TYPE' => 'text/xml'], $payload);

        $response->assertStatus(422)
                ->assertJsonPath('success', false)
                ->assertJsonPath('status_code', 422);

        expect(PunchoutSession::query()->count())->toBe(0);
});

it('rejects a cxml setup request with invalid configured to identity', function () {
        $tenant = Tenant::create([
                'name' => 'To Identity Tenant',
                'slug' => 'to-identity-tenant',
                'is_active' => true,
                'settings' => [
                        'punchout' => [
                                'default_protocol' => 'cxml',
                                'supported_protocols' => ['cxml'],
                                'protocols' => [
                                        'cxml' => [
                                                'credentials' => [
                                                        'to_identity' => 'expected-target-system',
                                                ],
                                        ],
                                ],
                        ],
                ],
        ]);

        $payload = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<cXML>
    <Request>
        <PunchOutSetupRequest>
            <From>
                <Credential>
                    <Identity>buyer-system</Identity>
                </Credential>
            </From>
            <To>
                <Credential>
                    <Identity>unexpected-target-system</Identity>
                </Credential>
            </To>
            <BuyerCookie>buyer-cookie-123</BuyerCookie>
            <BrowserFormPost>
                <URL>https://buyer.example/punchout/return</URL>
            </BrowserFormPost>
        </PunchOutSetupRequest>
    </Request>
</cXML>
XML;

        $response = $this->call('POST', '/api/v1/punchout/' . $tenant->uuid . '/setup?protocol=cxml', [], [], [], ['CONTENT_TYPE' => 'text/xml'], $payload);

        $response->assertStatus(422)
                ->assertJsonPath('success', false)
                ->assertJsonPath('status_code', 422);

        expect(PunchoutSession::query()->count())->toBe(0);
});

it('returns an oci setup response for a tenant', function () {
    config()->set('app.url', 'https://supplier.example');

    $tenant = Tenant::create([
        'name' => 'OCI Tenant',
        'slug' => 'oci-tenant',
        'is_active' => true,
        'settings' => [
            'punchout' => [
                'default_protocol' => 'oci',
                'supported_protocols' => ['oci', 'cxml'],
                'protocols' => [
                    'oci' => [
                        'catalog_url' => 'https://manage.example/punchout/catalog',
                    ],
                ],
            ],
        ],
    ]);

    $response = $this->post('/api/v1/punchout/' . $tenant->uuid . '/setup?protocol=oci', [
        'HOOK_URL' => 'https://buyer.example/oci/hook',
        'BUYER_COOKIE' => 'oci-cookie-123',
    ]);

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/html');
    $response->assertSee('https://supplier.example/api/v1/punchout/' . $tenant->uuid . '/start?session=', false);
    $response->assertSee('HOOK_URL', false);
});

it('rejects an oci setup request with invalid configured credentials', function () {
    $tenant = Tenant::create([
        'name' => 'OCI Credential Tenant',
        'slug' => 'oci-credential-tenant',
        'is_active' => true,
        'settings' => [
            'punchout' => [
                'default_protocol' => 'oci',
                'supported_protocols' => ['oci'],
                'protocols' => [
                    'oci' => [
                        'credentials' => [
                            'username' => 'expected-user',
                            'password' => 'expected-pass',
                        ],
                    ],
                ],
            ],
        ],
    ]);

    $response = $this->post('/api/v1/punchout/' . $tenant->uuid . '/setup?protocol=oci', [
        'HOOK_URL' => 'https://buyer.example/oci/hook',
        'BUYER_COOKIE' => 'oci-cookie-123',
        'USERNAME' => 'wrong-user',
        'PASSWORD' => 'wrong-pass',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonPath('status_code', 422);

    expect(PunchoutSession::query()->count())->toBe(0);
});

it('resolves a punchout start session as json when no catalog handoff is configured', function () {
    config()->set('app.url', 'https://supplier.example');

    $tenant = Tenant::create([
        'name' => 'Start Tenant',
        'slug' => 'start-tenant',
        'is_active' => true,
        'settings' => [
            'punchout' => [
                'default_protocol' => 'cxml',
                'supported_protocols' => ['cxml'],
            ],
        ],
    ]);

    $payload = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<cXML>
  <Request>
    <PunchOutSetupRequest>
      <BuyerCookie>start-cookie-123</BuyerCookie>
      <BrowserFormPost>
        <URL>https://buyer.example/punchout/return</URL>
      </BrowserFormPost>
    </PunchOutSetupRequest>
  </Request>
</cXML>
XML;

    $setupResponse = $this->call('POST', '/api/v1/punchout/' . $tenant->uuid . '/setup?protocol=cxml', [], [], [], ['CONTENT_TYPE' => 'text/xml'], $payload);

    preg_match('/<URL>(.*?)<\/URL>/', $setupResponse->getContent(), $matches);

    expect($matches)->toHaveCount(2);

    $startResponse = $this->get($matches[1]);

    $startResponse->assertOk();
    $startResponse->assertJsonPath('success', true);
    $startResponse->assertJsonPath('data.protocol', 'cxml');
    $startResponse->assertJsonPath('data.status', 'started');
    $startResponse->assertJsonPath('data.buyer_cookie', 'start-cookie-123');
});

it('rejects an expired punchout start session and marks it expired', function () {
    $tenant = Tenant::create([
        'name' => 'Expired Start Tenant',
        'slug' => 'expired-start-tenant',
        'is_active' => true,
        'settings' => [
            'punchout' => [
                'default_protocol' => 'cxml',
                'supported_protocols' => ['cxml'],
            ],
        ],
    ]);

    $session = PunchoutSession::create([
        'tenant_id' => $tenant->id,
        'protocol' => 'cxml',
        'status' => 'pending',
        'buyer_cookie' => 'expired-cookie',
        'return_url' => 'https://buyer.example/punchout/return',
        'expires_at' => now()->subMinute(),
        'last_activity_at' => now()->subMinutes(2),
    ]);

    $response = $this->get('/api/v1/punchout/' . $tenant->uuid . '/start?session=' . $session->uuid . '&token=' . $session->token);

    $response->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonPath('status_code', 422);

    expect($session->fresh()?->status)->toBe('expired');
});

it('redirects a punchout start session to a configured catalog handoff url', function () {
    config()->set('app.url', 'https://supplier.example');

    $tenant = Tenant::create([
        'name' => 'Redirect Tenant',
        'slug' => 'redirect-tenant',
        'is_active' => true,
        'settings' => [
            'punchout' => [
                'default_protocol' => 'oci',
                'supported_protocols' => ['oci'],
                'protocols' => [
                    'oci' => [
                        'catalog_url' => 'https://manage.example/punchout/catalog',
                    ],
                ],
            ],
        ],
    ]);

    $setupResponse = $this->post('/api/v1/punchout/' . $tenant->uuid . '/setup?protocol=oci', [
        'HOOK_URL' => 'https://buyer.example/oci/hook',
        'BUYER_COOKIE' => 'redirect-cookie-123',
    ]);

    preg_match('/action="([^"]+)"/', $setupResponse->getContent(), $actionMatches);

    expect($actionMatches)->toHaveCount(2);

    parse_str(parse_url(html_entity_decode($actionMatches[1], ENT_QUOTES | ENT_HTML5), PHP_URL_QUERY) ?? '', $query);

    $startResponse = $this->get('/api/v1/punchout/' . $tenant->uuid . '/start?' . http_build_query($query));

    $startResponse->assertRedirect();
    expect($startResponse->headers->get('location'))->toContain('https://manage.example/punchout/catalog');
    expect($startResponse->headers->get('location'))->toContain('session=');
    expect($startResponse->headers->get('location'))->toContain('token=');
});

it('returns a cxml cart form post response', function () {
    $tenant = Tenant::create([
        'name' => 'Acme Tenant',
        'slug' => 'acme-tenant',
        'is_active' => true,
        'settings' => [
            'punchout' => [
                'default_protocol' => 'cxml',
                'supported_protocols' => ['cxml', 'oci'],
            ],
        ],
    ]);

    $session = createStartedPunchoutSession($tenant, 'cxml', 'buyer-cookie-123', 'https://buyer.example/cxml/cart');

    $response = $this->post('/api/v1/punchout/' . $tenant->uuid . '/cart?protocol=cxml', [
        'session' => $session->uuid,
        'token' => $session->token,
        'return_url' => 'https://buyer.example/cxml/cart',
        'buyer_cookie' => 'buyer-cookie-123',
        'items' => [
            [
                'supplier_part_id' => 'SKU-1',
                'description' => 'Punchout Drill',
                'quantity' => 2,
                'price' => '19.99',
                'currency' => 'USD',
                'unit_of_measure' => 'EA',
            ],
        ],
    ]);

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/html');
    $response->assertSee('cXML-urlencoded', false);
    $response->assertSee('SKU-1', false);
    $response->assertSee('https://buyer.example/cxml/cart', false);
    expect($session->fresh()?->status)->toBe('completed');
    expect($session->fresh()?->completed_at)->not->toBeNull();
    expect($session->fresh()?->cart_returned_at)->not->toBeNull();
    expect($session->fresh()?->cart_items)->toHaveCount(1);
    expect(data_get($session->fresh()?->cart_items, '0.supplier_part_id'))->toBe('SKU-1');
    expect(data_get($session->fresh()?->cart_payload, 'operation'))->toBe('PunchOutOrderMessage');
    expect(data_get($session->fresh()?->cart_payload, 'return_url'))->toBe('https://buyer.example/cxml/cart');
});

it('returns an oci cart form post response', function () {
    $tenant = Tenant::create([
        'name' => 'OCI Tenant',
        'slug' => 'oci-tenant',
        'is_active' => true,
        'settings' => [
            'punchout' => [
                'default_protocol' => 'oci',
                'supported_protocols' => ['oci', 'cxml'],
            ],
        ],
    ]);

    $session = createStartedPunchoutSession($tenant, 'oci', 'buyer-cookie-oci', 'https://buyer.example/oci/cart');

    $response = $this->post('/api/v1/punchout/' . $tenant->uuid . '/cart?protocol=oci', [
        'session' => $session->uuid,
        'token' => $session->token,
        'HOOK_URL' => 'https://buyer.example/oci/cart',
        'BUYER_COOKIE' => 'buyer-cookie-oci',
        'items' => [
            [
                'supplier_part_id' => 'SKU-OCI-1',
                'description' => 'Punchout Saw',
                'quantity' => 1,
                'price' => '29.99',
                'currency' => 'USD',
                'unit_of_measure' => 'EA',
            ],
        ],
    ]);

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/html');
    $response->assertSee('NEW_ITEM-DESCRIPTION[1]', false);
    $response->assertSee('SKU-OCI-1', false);
    $response->assertSee('https://buyer.example/oci/cart', false);
    expect($session->fresh()?->status)->toBe('completed');
    expect($session->fresh()?->cart_returned_at)->not->toBeNull();
    expect($session->fresh()?->cart_items)->toHaveCount(1);
    expect(data_get($session->fresh()?->cart_items, '0.supplier_part_id'))->toBe('SKU-OCI-1');
    expect(data_get($session->fresh()?->cart_payload, 'operation'))->toBe('BACKGROUND_POST');
    expect(data_get($session->fresh()?->cart_payload, 'return_url'))->toBe('https://buyer.example/oci/cart');
});

it('rejects a cart request for a session that has not been started', function () {
    $tenant = Tenant::create([
        'name' => 'Pending Cart Tenant',
        'slug' => 'pending-cart-tenant',
        'is_active' => true,
        'settings' => [
            'punchout' => [
                'default_protocol' => 'cxml',
                'supported_protocols' => ['cxml'],
            ],
        ],
    ]);

    $session = PunchoutSession::create([
        'tenant_id' => $tenant->id,
        'protocol' => 'cxml',
        'status' => 'pending',
        'buyer_cookie' => 'buyer-cookie-123',
        'return_url' => 'https://buyer.example/cxml/cart',
        'last_activity_at' => now(),
    ]);

    $response = $this->post('/api/v1/punchout/' . $tenant->uuid . '/cart?protocol=cxml', [
        'session' => $session->uuid,
        'token' => $session->token,
        'return_url' => 'https://buyer.example/cxml/cart',
        'buyer_cookie' => 'buyer-cookie-123',
        'items' => [
            [
                'supplier_part_id' => 'SKU-1',
                'description' => 'Punchout Drill',
                'quantity' => 2,
                'price' => '19.99',
                'currency' => 'USD',
                'unit_of_measure' => 'EA',
            ],
        ],
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonPath('status_code', 422);

    expect($session->fresh()?->status)->toBe('pending');
});

it('rejects a cart request for an expired session and marks it expired', function () {
    $tenant = Tenant::create([
        'name' => 'Expired Cart Tenant',
        'slug' => 'expired-cart-tenant',
        'is_active' => true,
        'settings' => [
            'punchout' => [
                'default_protocol' => 'oci',
                'supported_protocols' => ['oci'],
            ],
        ],
    ]);

    $session = PunchoutSession::create([
        'tenant_id' => $tenant->id,
        'protocol' => 'oci',
        'status' => 'started',
        'buyer_cookie' => 'buyer-cookie-oci',
        'return_url' => 'https://buyer.example/oci/cart',
        'expires_at' => now()->subMinute(),
        'started_at' => now()->subMinutes(5),
        'last_activity_at' => now()->subMinutes(2),
    ]);

    $response = $this->post('/api/v1/punchout/' . $tenant->uuid . '/cart?protocol=oci', [
        'session' => $session->uuid,
        'token' => $session->token,
        'HOOK_URL' => 'https://buyer.example/oci/cart',
        'BUYER_COOKIE' => 'buyer-cookie-oci',
        'items' => [
            [
                'supplier_part_id' => 'SKU-OCI-1',
                'description' => 'Punchout Saw',
                'quantity' => 1,
                'price' => '29.99',
                'currency' => 'USD',
                'unit_of_measure' => 'EA',
            ],
        ],
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonPath('status_code', 422);

    expect($session->fresh()?->status)->toBe('expired');
});
