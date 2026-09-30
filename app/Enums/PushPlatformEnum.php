<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where a device's push token can be delivered.
 *
 * The values are what the storefront registers (`POST /api/v1/devices`) and what
 * `device_tokens.platform` holds. Each one maps to a different transport, a
 * different credential and a different failure mode, which is why routing goes
 * through this rather than comparing strings.
 */
enum PushPlatformEnum: string
{
    case ANDROID = 'android';
    case IOS = 'ios';

    public function label(): string
    {
        return match ($this) {
            self::ANDROID => 'Android (FCM)',
            self::IOS => 'iOS (APNs)',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::ANDROID => 'success',
            self::IOS => 'info',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * The `config/push.php` section holding this transport's credentials.
     */
    public function configKey(): string
    {
        return match ($this) {
            self::ANDROID => 'fcm',
            self::IOS => 'apns',
        };
    }
}
