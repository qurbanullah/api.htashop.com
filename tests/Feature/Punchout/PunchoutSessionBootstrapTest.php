<?php

namespace Tests\Feature\Punchout;

use App\Actions\Punchout\IssueHandoffCodeAction;

it('exchanges a handoff code for an access token and bootstraps the session', function () {
    $tenant = punchoutTenant('bootstrap-tenant');
    $session = punchoutSession($tenant);
    $code = app(IssueHandoffCodeAction::class)->handle($session);

    $response = $this->postJson('/api/v1/punchout/sessions/exchange', ['code' => $code]);

    $response->assertOk()
        ->assertJsonPath('data.session.uuid', $session->uuid)
        ->assertJsonPath('data.tenant.uuid', $tenant->uuid)
        ->assertJsonPath('data.catalog.channel', 'punchout');

    $token = (string) $response->json('data.session.access_token');

    expect($token)->not->toBe('');

    $sessionPayload = $response->json('data.session');
    expect($sessionPayload)->not->toHaveKey('token');
    expect($sessionPayload)->not->toHaveKey('token_hash');
    expect($sessionPayload)->not->toHaveKey('access_token_hash');

    $this->withHeader('Authorization', 'Punchout '.$token)
        ->getJson('/api/v1/punchout/sessions/'.$session->uuid)
        ->assertOk()
        ->assertJsonPath('data.session.uuid', $session->uuid)
        ->assertJsonPath('data.session.access_token', null);
});

it('rejects a reused handoff code', function () {
    $tenant = punchoutTenant('reused-code-tenant');
    $session = punchoutSession($tenant);
    $code = app(IssueHandoffCodeAction::class)->handle($session);

    $this->postJson('/api/v1/punchout/sessions/exchange', ['code' => $code])->assertOk();
    $this->postJson('/api/v1/punchout/sessions/exchange', ['code' => $code])->assertStatus(422);
});

it('rejects an expired handoff code', function () {
    $tenant = punchoutTenant('expired-code-tenant');
    $session = punchoutSession($tenant);
    $code = app(IssueHandoffCodeAction::class)->handle($session);

    $session->update(['handoff_code_expires_at' => now()->subMinute()]);

    $this->postJson('/api/v1/punchout/sessions/exchange', ['code' => $code])->assertStatus(422);
});

it('requires a token for session and catalog routes', function () {
    $tenant = punchoutTenant('no-token-tenant');
    $session = punchoutSession($tenant);

    $this->getJson('/api/v1/punchout/sessions/'.$session->uuid)->assertUnauthorized();
    $this->getJson('/api/v1/punchout/'.$tenant->uuid.'/catalog/products')->assertUnauthorized();
});

it('extends and cancels a session', function () {
    $tenant = punchoutTenant('lifecycle-tenant');
    $session = punchoutSession($tenant);
    $code = app(IssueHandoffCodeAction::class)->handle($session);
    $token = (string) $this->postJson('/api/v1/punchout/sessions/exchange', ['code' => $code])
        ->json('data.session.access_token');

    $this->withHeader('Authorization', 'Punchout '.$token)
        ->postJson('/api/v1/punchout/sessions/'.$session->uuid.'/heartbeat')
        ->assertOk()
        ->assertJsonPath('data.expires_at', $session->expires_at->toIso8601String());

    $this->withHeader('Authorization', 'Punchout '.$token)
        ->postJson('/api/v1/punchout/sessions/'.$session->uuid.'/cancel')
        ->assertOk();

    expect($session->fresh()->status)->toBe('cancelled');
    expect($session->fresh()->cancelled_at)->not->toBeNull();

    $this->withHeader('Authorization', 'Punchout '.$token)
        ->getJson('/api/v1/punchout/sessions/'.$session->uuid)
        ->assertStatus(422);
});
