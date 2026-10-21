<?php

declare(strict_types=1);

namespace App\Support\Analytics;

/**
 * Coarse client classification derived from the User-Agent.
 *
 * Deliberately dependency-free and deliberately coarse: the values become Loki
 * labels, so they must stay low-cardinality. A full UA database would add
 * thousands of versions for no analytical benefit.
 */
final readonly class ClientContext
{
    public function __construct(
        public string $deviceType,
        public string $browser,
        public string $platform,
        public bool $isBot,
    ) {}

    public static function fromUserAgent(?string $userAgent): self
    {
        $ua = strtolower(trim((string) $userAgent));

        if ($ua === '') {
            return new self('unknown', 'unknown', 'unknown', false);
        }

        $isBot = preg_match(
            '/bot|crawler|spider|slurp|bingpreview|facebookexternalhit|whatsapp|telegrambot|headless|phantom|selenium|puppeteer|playwright|googlebot|bingbot|yandex|baidu|duckduck|semrush|ahrefs|mj12|uptime|curl|wget|python-requests|go-http-client|java\//',
            $ua,
        ) === 1;

        return new self(
            deviceType: self::deviceType($ua, $isBot),
            browser: self::browser($ua),
            platform: self::platform($ua),
            isBot: $isBot,
        );
    }

    public static function fromClientAndUserAgent(?array $client, ?string $userAgent): self
    {
        $parsed = self::fromUserAgent($userAgent);

        $deviceType = self::safeValue(data_get($client, 'type')) ?? $parsed->deviceType;
        $browser = self::safeValue(data_get($client, 'browser')) ?? $parsed->browser;
        $platform = self::safeValue(data_get($client, 'os')) ?? $parsed->platform;

        return new self($deviceType, $browser, $platform, $parsed->isBot);
    }

    /**
     * @return array{device_type: string, browser: string, platform: string, is_bot: bool}
     */
    public function toArray(): array
    {
        return [
            'device_type' => $this->deviceType,
            'browser' => $this->browser,
            'platform' => $this->platform,
            'is_bot' => $this->isBot,
        ];
    }

    private static function deviceType(string $ua, bool $isBot): string
    {
        if ($isBot) {
            return 'bot';
        }

        if (preg_match('/ipad|tablet|kindle|silk|playbook/', $ua) === 1) {
            return 'tablet';
        }

        if (preg_match('/mobile|iphone|ipod|android.*mobile|windows phone|blackberry|opera mini/', $ua) === 1) {
            return 'mobile';
        }

        return 'desktop';
    }

    private static function browser(string $ua): string
    {
        return match (true) {
            str_contains($ua, 'edg/') => 'Edge',
            str_contains($ua, 'opr/'), str_contains($ua, 'opera') => 'Opera',
            str_contains($ua, 'chrome/'), str_contains($ua, 'crios/') => 'Chrome',
            str_contains($ua, 'firefox/'), str_contains($ua, 'fxios/') => 'Firefox',
            str_contains($ua, 'safari/') => 'Safari',
            default => 'Other',
        };
    }

    private static function platform(string $ua): string
    {
        return match (true) {
            str_contains($ua, 'windows') => 'Windows',
            str_contains($ua, 'android') => 'Android',
            str_contains($ua, 'iphone'), str_contains($ua, 'ipad'), str_contains($ua, 'ipod') => 'iOS',
            str_contains($ua, 'mac os'), str_contains($ua, 'macintosh') => 'macOS',
            str_contains($ua, 'linux') => 'Linux',
            default => 'Other',
        };
    }

    private static function safeValue(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = strtolower(trim($value));

        return preg_match('/^[a-z0-9 _-]{1,30}$/', $value) === 1 ? $value : null;
    }
}
