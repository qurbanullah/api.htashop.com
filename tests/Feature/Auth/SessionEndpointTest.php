<?php

use App\Models\User;

/**
 * The session endpoints the storefront relies on: the account it re-validates on
 * boot, and the sign-out that must actually revoke the session.
 *
 * The personal-access-client and sign-in helpers live in tests/Pest.php.
 */
it('returns the account in the standard envelope', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'api')
        ->getJson('/api/v1/user')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.email', $user->email)
        ->assertJsonPath('data.uuid', $user->uuid);
});

it('keeps the legacy top-level user key for older clients', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'api')
        ->getJson('/api/v1/user')
        ->assertOk()
        ->assertJsonPath('user.email', $user->email);
});

it('rejects an anonymous session check', function () {
    $this->getJson('/api/v1/user')->assertUnauthorized();
});

it('forgets the storefront access-token cookie on sign-out', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'api')->postJson('/api/v1/logout');

    $response->assertOk()->assertJsonPath('success', true);

    $cookie = collect($response->headers->getCookies())
        ->first(fn ($cookie) => $cookie->getName() === 'hta_access_token');

    // An expired, empty cookie is what actually ends the browser session.
    expect($cookie)->not->toBeNull()
        ->and($cookie->getValue())->toBe('')
        ->and($cookie->getExpiresTime())->toBeLessThan(time());
});

it('rejects a sign-out without a session', function () {
    $this->postJson('/api/v1/logout')->assertUnauthorized();
});

it('signs out only the device that asked', function () {
    $user = User::factory()->create();

    $deviceA = signInDevice($user->email)['access_token'];
    $deviceB = signInDevice($user->email)['access_token'];

    expect($deviceA)->not->toBe($deviceB)
        ->and($deviceA)->not->toBeEmpty();

    $this->withToken($deviceA)->getJson('/api/v1/user')->assertOk();
    $this->withToken($deviceB)->getJson('/api/v1/user')->assertOk();

    $this->withToken($deviceA)->postJson('/api/v1/logout')->assertOk();

    // One test case shares a single container, so the guard keeps the user it
    // already resolved (in production each request is its own process). Drop it
    // to make the next call re-validate the token.
    app('auth')->forgetGuards();

    // The device that signed out is done; the other one is untouched.
    $this->withToken($deviceA)->getJson('/api/v1/user')->assertUnauthorized();
    $this->withToken($deviceB)->getJson('/api/v1/user')->assertOk();
});

it('revokes the token rather than deleting it, leaving other devices intact', function () {
    $user = User::factory()->create();

    $deviceA = signInDevice($user->email)['access_token'];
    $deviceB = signInDevice($user->email)['access_token'];

    $this->withToken($deviceA)->postJson('/api/v1/logout')->assertOk();

    expect($user->tokens()->count())->toBe(2)
        ->and($user->tokens()->where('revoked', true)->count())->toBe(1);
});
