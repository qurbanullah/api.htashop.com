<?php

declare(strict_types=1);

use App\Jobs\Push\SendPushNotificationJob;
use App\Models\DeviceToken;
use App\Models\User;
use App\Services\Push\PushService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Android push delivery.
 *
 * A send is two calls — exchange the service account for an access token, then post
 * the message — so both are faked, and the assertions are about what actually went
 * out. The service-account file is a real RSA key generated per test, so the JWT
 * signing path runs rather than being stubbed.
 *
 * Each test registers its own fakes. `Http::fake()` *merges* stubs rather than
 * replacing them, so a fake set in beforeEach would be matched before anything a
 * test registered later — which silently turns a 404 into a 200.
 */

/** Writes a throwaway service-account file and returns its path. */
function fakeServiceAccount(): string
{
    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($key, $privateKey);

    $path = tempnam(sys_get_temp_dir(), 'fcm-service-account');

    file_put_contents($path, (string) json_encode([
        'type' => 'service_account',
        'project_id' => 'htashop-test',
        'private_key' => $privateKey,
        'client_email' => 'firebase-adminsdk@htashop-test.iam.gserviceaccount.com',
        'token_uri' => 'https://oauth2.googleapis.com/token',
    ]));

    return $path;
}

function androidDevice(User $user, string $token = 'fcm-token-abcdefghijklmnop'): DeviceToken
{
    return DeviceToken::create([
        'user_id' => $user->getKey(),
        'token' => $token,
        'platform' => 'android',
        'device_id' => 'device-1',
        'app_version' => '1.0',
    ]);
}

/** The token exchange every send needs first. */
function fakeTokenExchange(): void
{
    Http::fake([
        'oauth2.googleapis.com/token' => Http::response([
            'access_token' => 'access-token-1',
            'expires_in' => 3600,
            'token_type' => 'Bearer',
        ]),
    ]);
}

beforeEach(function (): void {
    config(['push.enabled' => true, 'push.fcm.project_id' => null]);

    // A stray request here would be a real notification on a real device.
    Http::preventStrayRequests();

    $this->serviceAccount = fakeServiceAccount();
    config(['push.fcm.credentials' => $this->serviceAccount]);
});

afterEach(function (): void {
    @unlink($this->serviceAccount);
});

it('sends the payload the device expects', function (): void {
    fakeTokenExchange();
    Http::fake([
        'fcm.googleapis.com/*' => Http::response(['name' => 'projects/htashop-test/messages/1']),
    ]);

    $device = androidDevice(User::factory()->create());

    $result = app(PushService::class)->sendToDevice(
        $device,
        'Order shipped',
        'Your order is on its way',
        ['link' => '/account/orders/1'],
    );

    expect($result->delivered)->toBeTrue();

    Http::assertSent(function ($request): bool {
        if (! str_contains($request->url(), 'messages:send')) {
            return false;
        }

        $message = $request->data()['message'];

        return $message['token'] === 'fcm-token-abcdefghijklmnop'
            && $message['notification']['title'] === 'Order shipped'
            && $message['notification']['body'] === 'Your order is on its way'
            && $message['data']['link'] === '/account/orders/1'
            && $message['android']['priority'] === 'high'
            && $request->hasHeader('Authorization', 'Bearer access-token-1');
    });

    // Stamped, so "when did this device last work" is answerable from support.
    expect($device->fresh()->last_used_at)->not->toBeNull();
});

it('normalises a non-string data value rather than sending something FCM rejects', function (): void {
    fakeTokenExchange();
    Http::fake([
        'fcm.googleapis.com/*' => Http::response(['name' => 'projects/htashop-test/messages/1']),
    ]);

    app(PushService::class)->sendToDevice(
        androidDevice(User::factory()->create()),
        'Title',
        'Body',
        ['order_id' => 42, 'nested' => ['a' => 1], 'missing' => null],
    );

    Http::assertSent(function ($request): bool {
        // assertSent runs for every recorded request, including the token exchange.
        if (! str_contains($request->url(), 'messages:send')) {
            return false;
        }

        $data = $request->data()['message']['data'] ?? [];

        // FCM's data map is string-to-string: an int or a nested array is rejected
        // outright, and a null is dropped.
        return ($data['order_id'] ?? null) === '42'
            && ($data['nested'] ?? null) === '{"a":1}'
            && ! array_key_exists('missing', $data);
    });
});

it('signs the assertion and reuses one access token across sends', function (): void {
    fakeTokenExchange();
    Http::fake([
        'fcm.googleapis.com/*' => Http::response(['name' => 'projects/htashop-test/messages/1']),
    ]);

    $user = User::factory()->create();

    $push = app(PushService::class);
    $push->sendToDevice(androidDevice($user, 'fcm-token-one-abcdefghijk'), 'One', 'First');
    $push->sendToDevice(androidDevice($user, 'fcm-token-two-abcdefghijk'), 'Two', 'Second');

    // One exchange, two sends: minting per device is what makes a fan-out slow and
    // gets the service account rate-limited.
    Http::assertSentCount(3);

    Http::assertSent(fn ($request): bool => str_contains($request->url(), 'oauth2.googleapis.com')
        && substr_count((string) $request->data()['assertion'], '.') === 2
        && $request->data()['grant_type'] === 'urn:ietf:params:oauth:grant-type:jwt-bearer');
});

it('forgets a token FCM reports as unregistered', function (): void {
    fakeTokenExchange();
    Http::fake([
        'fcm.googleapis.com/*' => Http::response([
            'error' => [
                'status' => 'NOT_FOUND',
                'details' => [['errorCode' => 'UNREGISTERED']],
            ],
        ], 404),
    ]);

    $device = androidDevice(User::factory()->create(), 'fcm-token-dead-abcdefghij');

    $result = app(PushService::class)->sendToDevice($device, 'Title', 'Body');

    expect($result->delivered)->toBeFalse()
        ->and($result->shouldPrune())->toBeTrue()
        ->and($result->detail)->toBe('HTTP 404 UNREGISTERED')
        // The row is gone: a token that can never work again must not be retried
        // on every notification forever.
        ->and(DeviceToken::query()->whereKey($device->getKey())->exists())->toBeFalse();
});

it('keeps a token when the failure looks temporary', function (): void {
    fakeTokenExchange();
    Http::fake([
        'fcm.googleapis.com/*' => Http::response(['error' => ['status' => 'INTERNAL']], 500),
    ]);

    $device = androidDevice(User::factory()->create(), 'fcm-token-busy-abcdefghij');

    $result = app(PushService::class)->sendToDevice($device, 'Title', 'Body');

    // Pruning on a 500 would unsubscribe a device that is working perfectly.
    expect($result->shouldPrune())->toBeFalse()
        ->and(DeviceToken::query()->whereKey($device->getKey())->exists())->toBeTrue();
});

it('retries once when the cached access token has expired', function (): void {
    Cache::put('push.fcm.access_token.htashop-test', 'stale-token', 600);

    Http::fake([
        'oauth2.googleapis.com/token' => Http::response(['access_token' => 'fresh-token', 'expires_in' => 3600]),
        'fcm.googleapis.com/*' => Http::sequence()
            ->push(['error' => ['status' => 'UNAUTHENTICATED']], 401)
            ->push(['name' => 'projects/htashop-test/messages/1']),
    ]);

    $result = app(PushService::class)->sendToDevice(androidDevice(User::factory()->create()), 'Title', 'Body');

    expect($result->delivered)->toBeTrue();

    // The first attempt used the stale token, the retry minted a new one.
    Http::assertSent(fn ($request): bool => str_contains($request->url(), 'oauth2.googleapis.com')
        && $request->data()['assertion'] !== '');
});

it('delivers through the queued job', function (): void {
    fakeTokenExchange();
    Http::fake([
        'fcm.googleapis.com/*' => Http::response(['name' => 'projects/htashop-test/messages/1']),
    ]);

    $user = User::factory()->create();
    androidDevice($user, 'fcm-token-job-abcdefghijkl');

    (new SendPushNotificationJob($user->getKey(), 'Order shipped', 'On its way', ['link' => '/x']))
        ->handle(app(PushService::class));

    Http::assertSent(fn ($request): bool => str_contains($request->url(), 'messages:send')
        && $request->data()['message']['token'] === 'fcm-token-job-abcdefghijkl'
        && $request->data()['message']['data']['link'] === '/x');
});

it('drops a notification for a user deleted before delivery', function (): void {
    // Nothing is faked here and stray requests are prevented, so a job that tried
    // to send would blow up rather than quietly pass.
    (new SendPushNotificationJob(999_999, 'Title', 'Body'))->handle(app(PushService::class));

    Http::assertNothingSent();
});

it('does nothing when push is switched off', function (): void {
    config(['push.enabled' => false]);

    $result = app(PushService::class)->sendToDevice(androidDevice(User::factory()->create()), 'Title', 'Body');

    expect($result->skipped)->toBeTrue();
    Http::assertNothingSent();
});

it('skips without credentials instead of failing', function (): void {
    config(['push.fcm.credentials' => '/nonexistent/service-account.json']);

    $result = app(PushService::class)->sendToDevice(androidDevice(User::factory()->create()), 'Title', 'Body');

    // A half-configured deployment is quiet, not broken.
    expect($result->skipped)->toBeTrue();
    Http::assertNothingSent();
});

it('reports iOS as skipped until APNs is configured', function (): void {
    $device = DeviceToken::create([
        'user_id' => User::factory()->create()->getKey(),
        'token' => str_repeat('a', 64),
        'platform' => 'ios',
    ]);

    $result = app(PushService::class)->sendToDevice($device, 'Title', 'Body');

    expect($result->skipped)->toBeTrue();
    Http::assertNothingSent();
});
