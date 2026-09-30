<?php

use App\Models\DeviceToken;
use App\Models\User;

it('rejects an anonymous device registration', function () {
    $this->postJson('/api/v1/devices', [
        'token' => 'fcm-token-aaaaaaaaaaaaaaaaaaaaaaaaaa',
        'platform' => 'android',
    ])->assertUnauthorized();
});

it('registers a device for the signed-in account', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'api')
        ->postJson('/api/v1/devices', [
            'token' => 'fcm-token-aaaaaaaaaaaaaaaaaaaaaaaaaa',
            'platform' => 'android',
            'device_id' => 'device-1',
            'app_version' => '1.0.0',
        ])
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['data' => ['uuid', 'platform']]);

    $device = DeviceToken::query()->firstOrFail();

    expect($device->user_id)->toBe($user->id)
        ->and($device->platform)->toBe('android')
        ->and($device->device_id)->toBe('device-1')
        ->and($device->last_used_at)->not->toBeNull();
});

it('is idempotent when the same device registers again', function () {
    $user = User::factory()->create();
    $payload = ['token' => 'fcm-token-aaaaaaaaaaaaaaaaaaaaaaaaaa', 'platform' => 'android'];

    $this->actingAs($user, 'api')->postJson('/api/v1/devices', $payload)->assertCreated();
    $this->actingAs($user, 'api')->postJson('/api/v1/devices', $payload)->assertCreated();

    expect(DeviceToken::query()->count())->toBe(1);
});

it('reassigns the token when another account signs in on the same device', function () {
    $first = User::factory()->create();
    $second = User::factory()->create();
    $payload = ['token' => 'fcm-token-aaaaaaaaaaaaaaaaaaaaaaaaaa', 'platform' => 'android'];

    $this->actingAs($first, 'api')->postJson('/api/v1/devices', $payload)->assertCreated();
    $this->actingAs($second, 'api')->postJson('/api/v1/devices', $payload)->assertCreated();

    expect(DeviceToken::query()->count())->toBe(1)
        ->and(DeviceToken::query()->firstOrFail()->user_id)->toBe($second->id);
});

it('retires the previous token when a device re-registers with a new one', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'api')->postJson('/api/v1/devices', [
        'token' => 'fcm-token-old-aaaaaaaaaaaaaaaaaaaaaa',
        'platform' => 'android',
        'device_id' => 'device-1',
    ])->assertCreated();

    $this->actingAs($user, 'api')->postJson('/api/v1/devices', [
        'token' => 'fcm-token-new-aaaaaaaaaaaaaaaaaaaaaa',
        'platform' => 'android',
        'device_id' => 'device-1',
    ])->assertCreated();

    expect(DeviceToken::query()->pluck('token')->all())
        ->toBe(['fcm-token-new-aaaaaaaaaaaaaaaaaaaaaa']);
});

it('validates the platform and the token', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'api')
        ->postJson('/api/v1/devices', ['token' => 'fcm-token-aaaaaaaaaaaaaaaaaaaaaaaaaa', 'platform' => 'windows'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('platform');

    $this->actingAs($user, 'api')
        ->postJson('/api/v1/devices', ['platform' => 'android'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('token');
});

it('detaches a device on sign-out', function () {
    $user = User::factory()->create();
    $token = 'fcm-token-aaaaaaaaaaaaaaaaaaaaaaaaaa';

    $this->actingAs($user, 'api')
        ->postJson('/api/v1/devices', ['token' => $token, 'platform' => 'ios'])
        ->assertCreated();

    $this->actingAs($user, 'api')
        ->deleteJson('/api/v1/devices', ['token' => $token])
        ->assertOk()
        ->assertJsonPath('data.deleted', true);

    expect(DeviceToken::query()->count())->toBe(0);
});

it('cannot detach another account\'s device', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $token = 'fcm-token-aaaaaaaaaaaaaaaaaaaaaaaaaa';

    $this->actingAs($owner, 'api')
        ->postJson('/api/v1/devices', ['token' => $token, 'platform' => 'android'])
        ->assertCreated();

    $this->actingAs($other, 'api')
        ->deleteJson('/api/v1/devices', ['token' => $token])
        ->assertOk()
        ->assertJsonPath('data.deleted', false);

    expect(DeviceToken::query()->count())->toBe(1);
});
