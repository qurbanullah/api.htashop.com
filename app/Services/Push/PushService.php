<?php

declare(strict_types=1);

namespace App\Services\Push;

use App\Enums\PushPlatformEnum;
use App\Models\DeviceToken;
use App\Models\User;
use App\Services\Push\Providers\FcmProvider;
use Illuminate\Support\Facades\Log;

/**
 * Delivery of notifications to a user's registered devices.
 *
 * One user can hold several devices across both platforms, so this fans out and
 * reports what happened rather than throwing: a push that cannot be delivered is
 * not a reason to fail the request that triggered it.
 *
 * Two things are deliberately skipped rather than failed, so that a half-configured
 * deployment is quiet instead of broken:
 *
 * - push disabled (`PUSH_ENABLED=false`), or
 * - the transport for that platform has no credentials (iOS until the Apple
 *   account exists).
 */
class PushService
{
    public function __construct(private readonly FcmProvider $fcm) {}

    /**
     * Sends to every device the user has registered.
     *
     * @param  array<string, mixed>  $data  extra payload keys, e.g. a deep link
     * @return array{delivered: int, failed: int, pruned: int, skipped: int}
     */
    public function sendToUser(User $user, string $title, string $body, array $data = []): array
    {
        $devices = DeviceToken::query()->where('user_id', $user->getKey())->get();

        return $this->sendToMany($devices->all(), $title, $body, $data);
    }

    /**
     * A single device — used when the caller already knows which one.
     *
     * @param  array<string, mixed>  $data
     */
    public function sendToDevice(DeviceToken $device, string $title, string $body, array $data = []): PushResult
    {
        if (! config('push.enabled')) {
            return PushResult::skipped('push is disabled');
        }

        $platform = PushPlatformEnum::tryFrom($device->platform) ?? null;

        if ($platform === null) {
            return PushResult::permanent("unknown platform '{$device->platform}'");
        }

        $provider = $this->providerFor($platform);

        if ($provider === null) {
            return PushResult::skipped("{$platform->configKey()} is not configured");
        }

        $result = $provider->send($device->token, $this->message($platform, $title, $body, $data));

        if ($result->delivered) {
            // Kept for the same reason the refresh-token table records use: a
            // support question about a device that stopped receiving is answered
            // by when it last worked.
            $device->forceFill(['last_used_at' => now()])->save();

            return $result;
        }

        if ($result->shouldPrune()) {
            Log::info('Pruning an undeliverable push token.', [
                'device_token_id' => $device->getKey(),
                'platform' => $device->platform,
                'detail' => $result->detail,
            ]);

            $device->delete();

            return $result;
        }

        Log::warning('Push delivery failed.', [
            'device_token_id' => $device->getKey(),
            'platform' => $device->platform,
            'detail' => $result->detail,
        ]);

        return $result;
    }

    /**
     * @param  array<int, DeviceToken>  $devices
     * @param  array<string, mixed>  $data
     * @return array{delivered: int, failed: int, pruned: int, skipped: int}
     */
    public function sendToMany(array $devices, string $title, string $body, array $data = []): array
    {
        $counts = ['delivered' => 0, 'failed' => 0, 'pruned' => 0, 'skipped' => 0];

        foreach ($devices as $device) {
            $result = $this->sendToDevice($device, $title, $body, $data);

            if ($result->delivered) {
                $counts['delivered']++;
            } elseif ($result->skipped) {
                $counts['skipped']++;
            } else {
                $counts['failed']++;
            }

            if ($result->shouldPrune()) {
                $counts['pruned']++;
            }
        }

        return $counts;
    }

    /**
     * Whether anything at all can be delivered right now.
     *
     * Lets a caller (a queue worker, an admin screen) say "push is off" instead of
     * reporting a wall of skipped sends.
     */
    public function isAvailable(): bool
    {
        return (bool) config('push.enabled') && $this->fcm->isConfigured();
    }

    /**
     * The payload each transport expects. They are not alike: FCM takes
     * `notification` + `data`, APNs takes `aps.alert` with the custom keys at the
     * top level.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function message(PushPlatformEnum $platform, string $title, string $body, array $data): array
    {
        return match ($platform) {
            PushPlatformEnum::ANDROID => [
                'notification' => ['title' => $title, 'body' => $body],
                // High priority so a foregrounded device wakes immediately; the
                // storefront's own copy is what the user reads.
                'android' => ['priority' => 'high'],
                'data' => $this->stringify($data),
            ],
            PushPlatformEnum::IOS => [
                'aps' => ['alert' => ['title' => $title, 'body' => $body], 'sound' => 'default'],
            ] + $this->stringify($data),
        };
    }

    /**
     * FCM's `data` map is string-to-string: a nested array or an integer is
     * rejected outright, so callers can pass whatever is natural and it is
     * normalised here rather than at every call site.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    private function stringify(array $data): array
    {
        $stringified = [];

        foreach ($data as $key => $value) {
            if ($value === null) {
                continue;
            }

            $stringified[(string) $key] = is_scalar($value)
                ? (string) $value
                : (string) json_encode($value);
        }

        return $stringified;
    }

    private function providerFor(PushPlatformEnum $platform): ?FcmProvider
    {
        // APNs arrives with the Apple account. Until then the only transport is FCM.
        return match ($platform) {
            PushPlatformEnum::ANDROID => $this->fcm->isConfigured() ? $this->fcm : null,
            PushPlatformEnum::IOS => null,
        };
    }
}
