<?php

use App\Enums\RefreshTokenRevokedReasonEnum;
use App\Models\RefreshToken;
use App\Models\User;

/**
 * The refresh-token flow: short-lived access tokens carried by a long-lived,
 * single-use refresh token.
 *
 * The security-relevant behaviour is what happens when a token is presented
 * twice — that is the only signal distinguishing a lost response from a stolen
 * token, and both cases are covered below.
 */
beforeEach(function () {
    // Minting an access token needs a Passport personal-access client.
    ensurePersonalAccessClient();
});

describe('sign-in', function () {
    it('issues an access token and a single-use refresh token', function () {
        $user = User::factory()->create();

        $response = $this->withHeader('X-Client', 'native')
            ->postJson('/api/v1/login', ['email' => $user->email, 'password' => 'password'])
            ->assertOk();

        expect($response->json('data.access_token'))->toBeString()->not->toBeEmpty()
            ->and($response->json('data.refresh_token'))->toBeString()->not->toBeEmpty()
            ->and($response->json('data.token_type'))->toBe('Bearer')
            // The access token is deliberately short-lived; the refresh token
            // carries the session instead.
            ->and($response->json('data.expires_in'))->toBe((int) config('auth_tokens.access_ttl'))
            ->and($response->json('data.refresh_expires_in'))->toBeGreaterThan(0);

        $stored = RefreshToken::query()->sole();

        // Only the hash is persisted — a database read cannot be replayed.
        expect($stored->token_hash)->not->toBe($response->json('data.refresh_token'))
            ->and($stored->token_hash)->toBe(RefreshToken::hashToken((string) $response->json('data.refresh_token')))
            ->and($stored->user_id)->toBe($user->id)
            ->and($stored->access_token_id)->not->toBeNull()
            ->and($stored->revoked_at)->toBeNull();
    });

    it('gives the storefront cookies instead of tokens in the body', function () {
        $user = User::factory()->create();

        $response = $this->withHeader('X-Client', 'storefront')
            ->postJson('/api/v1/login', ['email' => $user->email, 'password' => 'password'])
            ->assertOk();

        expect($response->json('data.access_token'))->toBeNull()
            ->and($response->json('data.refresh_token'))->toBeNull()
            // Not secret, and the client needs it to refresh on time.
            ->and($response->json('data.expires_in'))->toBe((int) config('auth_tokens.access_ttl'));

        $cookies = collect($response->headers->getCookies())->keyBy(fn ($cookie) => $cookie->getName());

        expect($cookies->has('hta_access_token'))->toBeTrue()
            ->and($cookies->has('hta_refresh_token'))->toBeTrue()
            ->and($cookies->get('hta_access_token')->isHttpOnly())->toBeTrue()
            ->and($cookies->get('hta_refresh_token')->isHttpOnly())->toBeTrue()
            // The long-lived credential is only ever sent to the refresh endpoint.
            ->and($cookies->get('hta_refresh_token')->getPath())
            ->toBe((string) config('auth_tokens.cookie.refresh_path'));
    });
});

describe('rotation', function () {
    it('exchanges a refresh token for a new pair', function () {
        $user = User::factory()->create();
        $session = signInDevice($user->email);

        $response = $this->withHeader('X-Client', 'native')
            ->postJson('/api/v1/refresh', ['refresh_token' => $session['refresh_token']])
            ->assertOk();

        expect($response->json('data.access_token'))->toBeString()->not->toBeEmpty()
            ->and($response->json('data.access_token'))->not->toBe($session['access_token'])
            ->and($response->json('data.refresh_token'))->not->toBe($session['refresh_token']);

        // A refresh must not add to a session's lifetime — otherwise a stolen
        // token could be renewed forever.
        expect($response->json('data.refresh_expires_in'))->toBeLessThanOrEqual(
            (int) config('auth_tokens.refresh_ttl')
        );
    });

    it('accepts the token from the X-Refresh-Token header', function () {
        $user = User::factory()->create();
        $session = signInDevice($user->email);

        $this->withHeaders([
            'X-Client' => 'native',
            'X-Refresh-Token' => $session['refresh_token'],
        ])
            ->postJson('/api/v1/refresh')
            ->assertOk()
            ->assertJsonPath('success', true);
    });

    it('retires the previous access token when it rotates', function () {
        $user = User::factory()->create();
        $session = signInDevice($user->email);

        $this->withToken($session['access_token'])->getJson('/api/v1/user')->assertOk();

        $this->withHeader('X-Client', 'native')
            ->postJson('/api/v1/refresh', ['refresh_token' => $session['refresh_token']])
            ->assertOk();

        app('auth')->forgetGuards();

        // The access token that was replaced must not stay usable for the rest of
        // its TTL.
        $this->withToken($session['access_token'])->getJson('/api/v1/user')->assertUnauthorized();
    });

    it('keeps each rotation step in the same family', function () {
        $user = User::factory()->create();
        $session = signInDevice($user->email);

        $this->withHeader('X-Client', 'native')
            ->postJson('/api/v1/refresh', ['refresh_token' => $session['refresh_token']])
            ->assertOk();

        $rows = RefreshToken::query()->get();

        expect($rows)->toHaveCount(2)
            ->and($rows->pluck('family_id')->unique())->toHaveCount(1);
    });
});

describe('replay detection', function () {
    it('recovers when the successor was never used (the response was lost)', function () {
        $user = User::factory()->create();
        $session = signInDevice($user->email);

        // First refresh succeeds, but imagine the client never received it.
        $this->withHeader('X-Client', 'native')
            ->postJson('/api/v1/refresh', ['refresh_token' => $session['refresh_token']])
            ->assertOk();

        $orphan = RefreshToken::query()->orderByDesc('id')->firstOrFail();

        // The client retries with the token it still holds. Only one party can
        // have been refreshing, so the session continues rather than dying.
        $recovered = $this->withHeader('X-Client', 'native')
            ->postJson('/api/v1/refresh', ['refresh_token' => $session['refresh_token']])
            ->assertOk();

        expect($recovered->json('data.refresh_token'))->not->toBe($session['refresh_token'])
            ->and($orphan->fresh()->revoked_reason)->toBe(RefreshTokenRevokedReasonEnum::RECOVERED)
            ->and(RefreshToken::query()->pluck('family_id')->unique())->toHaveCount(1);

        // The recovered token works.
        $this->withHeader('X-Client', 'native')
            ->postJson('/api/v1/refresh', ['refresh_token' => $recovered->json('data.refresh_token')])
            ->assertOk();
    });

    it('revokes the whole family when a superseded token is used again', function () {
        $user = User::factory()->create();
        $session = signInDevice($user->email);

        $rotated = $this->withHeader('X-Client', 'native')
            ->postJson('/api/v1/refresh', ['refresh_token' => $session['refresh_token']])
            ->assertOk();

        // The successor is used, so two parties demonstrably hold tokens.
        $second = $this->withHeader('X-Client', 'native')
            ->postJson('/api/v1/refresh', ['refresh_token' => $rotated->json('data.refresh_token')])
            ->assertOk();

        $replay = $this->withHeader('X-Client', 'native')
            ->postJson('/api/v1/refresh', ['refresh_token' => $session['refresh_token']]);

        $replay->assertUnauthorized()->assertJsonPath('data.code', 'refresh_token_reused');

        // Everything from that lineage is dead — including the token the honest
        // client was holding, because we cannot tell which party is which.
        $this->withHeader('X-Client', 'native')
            ->postJson('/api/v1/refresh', ['refresh_token' => $second->json('data.refresh_token')])
            ->assertUnauthorized();

        expect(RefreshToken::query()->whereNull('revoked_at')->count())->toBe(0)
            ->and(RefreshToken::query()->where('revoked_reason', RefreshTokenRevokedReasonEnum::REUSE_DETECTED->value)->count())
            ->toBeGreaterThan(0);
    });

    it('does not treat a deliberately signed-out token as theft', function () {
        $user = User::factory()->create();
        $session = signInDevice($user->email);

        $this->withToken($session['access_token'])->postJson('/api/v1/logout')->assertOk();

        $this->withHeader('X-Client', 'native')
            ->postJson('/api/v1/refresh', ['refresh_token' => $session['refresh_token']])
            ->assertUnauthorized()
            // REVOKED, not REUSED: a sign-out is expected, not a compromise.
            ->assertJsonPath('data.code', 'refresh_token_revoked');

        expect(RefreshToken::query()->where('revoked_reason', RefreshTokenRevokedReasonEnum::REUSE_DETECTED->value)->count())
            ->toBe(0);
    });
});

describe('rejections', function () {
    it('reports a missing token as a 401, not a validation error', function () {
        $this->withHeader('X-Client', 'native')
            ->postJson('/api/v1/refresh')
            ->assertUnauthorized()
            ->assertJsonPath('data.code', 'refresh_token_missing');
    });

    it('rejects an unknown token', function () {
        $this->withHeader('X-Client', 'native')
            ->postJson('/api/v1/refresh', ['refresh_token' => str_repeat('a', 128)])
            ->assertUnauthorized()
            ->assertJsonPath('data.code', 'refresh_token_invalid');
    });

    it('rejects an expired token', function () {
        $user = User::factory()->create();
        $session = signInDevice($user->email);

        RefreshToken::query()->update(['expires_at' => now()->subMinute()]);

        $this->withHeader('X-Client', 'native')
            ->postJson('/api/v1/refresh', ['refresh_token' => $session['refresh_token']])
            ->assertUnauthorized()
            ->assertJsonPath('data.code', 'refresh_token_expired');
    });

    it('rejects a token left idle past the idle window', function () {
        $user = User::factory()->create();
        $session = signInDevice($user->email);

        $idleTtl = (int) config('auth_tokens.refresh_idle_ttl');

        RefreshToken::query()->update([
            'created_at' => now()->subSeconds($idleTtl + 60),
            'last_used_at' => now()->subSeconds($idleTtl + 60),
        ]);

        $this->withHeader('X-Client', 'native')
            ->postJson('/api/v1/refresh', ['refresh_token' => $session['refresh_token']])
            ->assertUnauthorized()
            ->assertJsonPath('data.code', 'refresh_token_idle_expired');
    });

    it('clears the cookies when it refuses a refresh', function () {
        $response = $this->withHeader('X-Client', 'storefront')
            ->postJson('/api/v1/refresh')
            ->assertUnauthorized();

        $names = collect($response->headers->getCookies())->map(fn ($cookie) => $cookie->getName())->all();

        expect($names)->toContain('hta_access_token', 'hta_refresh_token');
    });
});

describe('sign-out and password change', function () {
    it('ends the refresh lineage on sign-out', function () {
        $user = User::factory()->create();
        $session = signInDevice($user->email);

        $this->withToken($session['access_token'])->postJson('/api/v1/logout')->assertOk();

        expect(RefreshToken::query()->whereNull('revoked_at')->count())->toBe(0)
            ->and(RefreshToken::query()->sole()->revoked_reason)
            ->toBe(RefreshTokenRevokedReasonEnum::LOGOUT);
    });

    it('leaves other devices signed in when one signs out', function () {
        $user = User::factory()->create();
        $deviceA = signInDevice($user->email);
        $deviceB = signInDevice($user->email);

        $this->withToken($deviceA['access_token'])->postJson('/api/v1/logout')->assertOk();

        $this->withHeader('X-Client', 'native')
            ->postJson('/api/v1/refresh', ['refresh_token' => $deviceB['refresh_token']])
            ->assertOk();
    });

    it('ends other sessions on a password change but keeps the current one', function () {
        $user = User::factory()->create();
        $current = signInDevice($user->email);
        $other = signInDevice($user->email);

        $this->withToken($current['access_token'])
            ->postJson('/api/v1/account/change-password', [
                'current_password' => 'password',
                'new_password' => 'a-brand-new-password',
                'new_password_confirmation' => 'a-brand-new-password',
            ])
            ->assertOk();

        // The device that proved the old password keeps working...
        $this->withHeader('X-Client', 'native')
            ->postJson('/api/v1/refresh', ['refresh_token' => $current['refresh_token']])
            ->assertOk();

        // ...and the other one is cut off.
        $this->withHeader('X-Client', 'native')
            ->postJson('/api/v1/refresh', ['refresh_token' => $other['refresh_token']])
            ->assertUnauthorized()
            ->assertJsonPath('data.code', 'refresh_token_revoked');
    });
});

describe('pruning', function () {
    it('deletes only tokens that expired past the retention window', function () {
        $user = User::factory()->create();
        signInDevice($user->email);

        $retention = (int) config('auth_tokens.prune_after');

        $stale = RefreshToken::query()->sole();
        $stale->forceFill(['expires_at' => now()->subSeconds($retention + 3600)])->save();

        $fresh = RefreshToken::query()->create([
            'user_id' => $user->id,
            'token_hash' => RefreshToken::hashToken('fresh-token'),
            'family_id' => $stale->family_id,
            'expires_at' => now()->subMinute(),
        ]);

        $this->artisan('auth:prune-refresh-tokens')->assertSuccessful();

        // The recent-but-expired row is kept for audit; only the one past the
        // retention window goes.
        expect(RefreshToken::query()->pluck('id')->all())->toBe([$fresh->id]);
    });
});
