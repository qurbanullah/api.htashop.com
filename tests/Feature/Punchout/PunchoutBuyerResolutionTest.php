<?php

use App\Actions\Punchout\IssueHandoffCodeAction;
use App\Actions\Punchout\ValidatePunchoutReturnUrlAction;
use App\Models\PunchoutSession;
use App\Services\Punchout\PunchoutBuyerService;
use Illuminate\Validation\ValidationException;

/**
 * @param  array<string, string>  $overrides
 */
function punchoutSetupXml(array $overrides = []): string
{
    $sender = $overrides['sender'] ?? 'buyer-sender';
    $secret = $overrides['secret'] ?? 'buyer-shared-secret';
    $from = $overrides['from'] ?? 'buyer-from';
    $to = $overrides['to'] ?? 'htashop';
    $returnUrl = $overrides['return_url'] ?? 'https://buyer.example/return';

    return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<cXML>
  <Header>
    <Sender>
      <Credential>
        <Identity>{$sender}</Identity>
        <SharedSecret>{$secret}</SharedSecret>
      </Credential>
    </Sender>
  </Header>
  <Request>
    <PunchOutSetupRequest>
      <BuyerCookie>buyer-cookie-123</BuyerCookie>
      <From><Credential><Identity>{$from}</Identity></Credential></From>
      <To><Credential><Identity>{$to}</Identity></Credential></To>
      <BrowserFormPost>
        <URL>{$returnUrl}</URL>
      </BrowserFormPost>
    </PunchOutSetupRequest>
  </Request>
</cXML>
XML;
}

it('attributes a setup request to a registered buyer', function () {
    config()->set('app.url', 'https://supplier.example');

    $tenant = punchoutTenant('attribution-tenant');
    $buyer = punchoutBuyer($tenant);

    $response = $this->call(
        'POST',
        '/api/v1/punchout/'.$tenant->uuid.'/setup?protocol=cxml',
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'text/xml'],
        punchoutSetupXml(),
    );

    $response->assertOk();

    $session = PunchoutSession::query()->firstOrFail();

    expect($session->buyer_id)->toBe($buyer->id);
    expect($buyer->fresh()->last_seen_at)->not->toBeNull();
});

it('rejects an unknown sender once a tenant has buyers', function () {
    $tenant = punchoutTenant('unknown-sender-tenant');
    punchoutBuyer($tenant);

    $this->call(
        'POST',
        '/api/v1/punchout/'.$tenant->uuid.'/setup?protocol=cxml',
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'text/xml'],
        punchoutSetupXml(['sender' => 'someone-else']),
    )
        ->assertStatus(422)
        ->assertJsonPath('success', false);

    expect(PunchoutSession::query()->count())->toBe(0);
});

it('accepts both secrets during a rotation overlap window', function () {
    $tenant = punchoutTenant('rotation-tenant');
    $buyer = punchoutBuyer($tenant);

    app(PunchoutBuyerService::class)->rotateSecret($buyer, 'new-shared-secret-value', true);

    foreach (['buyer-shared-secret', 'new-shared-secret-value'] as $secret) {
        $this->call(
            'POST',
            '/api/v1/punchout/'.$tenant->uuid.'/setup?protocol=cxml',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'text/xml'],
            punchoutSetupXml(['secret' => $secret]),
        )->assertOk();
    }

    expect(PunchoutSession::query()->count())->toBe(2);
    expect(PunchoutSession::query()->pluck('buyer_id')->unique()->all())->toBe([$buyer->id]);
});

it('bootstraps buyer overrides for branding, locale and prices', function () {
    $tenant = punchoutTenant('buyer-bootstrap-tenant');
    $buyer = punchoutBuyer($tenant, [
        'code' => 'acme',
        'default_currency' => 'EUR',
        'default_locale' => 'de',
        'settings' => [
            'hide_prices' => true,
            'customer_part_number_required' => false,
            'branding' => ['primary_color' => '#123456'],
        ],
    ]);
    $session = punchoutSession($tenant, 'cxml', 'https://buyer.example/return', $buyer);
    $code = app(IssueHandoffCodeAction::class)->handle($session);

    $this->postJson('/api/v1/punchout/sessions/exchange', ['code' => $code])
        ->assertOk()
        ->assertJsonPath('data.buyer.uuid', $buyer->uuid)
        ->assertJsonPath('data.buyer.code', 'acme')
        ->assertJsonPath('data.buyer.customer_part_number_required', false)
        ->assertJsonPath('data.catalog.currency', 'EUR')
        ->assertJsonPath('data.catalog.locale', 'de')
        ->assertJsonPath('data.catalog.hide_prices', true)
        ->assertJsonPath('data.branding.primary_color', '#123456');
});

it('enforces the buyer return-domain allowlist', function () {
    $tenant = punchoutTenant('allowlist-tenant');
    $buyer = punchoutBuyer($tenant, ['allowed_return_domains' => ['*.buyer.example']]);
    $session = punchoutSession($tenant, 'cxml', 'https://evil.example/return', $buyer);

    $action = app(ValidatePunchoutReturnUrlAction::class);

    expect(fn () => $action->handle($session))->toThrow(ValidationException::class);

    expect($buyer->allowsReturnHost('procurement.buyer.example'))->toBeTrue();
    expect($buyer->allowsReturnHost('buyer.example'))->toBeFalse();
    expect($buyer->allowsReturnHost('evil.example'))->toBeFalse();
});

it('keeps buyer credentials out of the session audit response', function () {
    $tenant = punchoutTenant('audit-secret-tenant');
    $buyer = punchoutBuyer($tenant);

    expect($buyer->toArray())->not->toHaveKey('shared_secret_hash');
    expect($buyer->toArray())->not->toHaveKey('oci_password_hash');
});
