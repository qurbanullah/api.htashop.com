<?php

namespace App\Gateways\Safepay;

/**
 * Verifies the signature Safepay sends on webhooks.
 *
 * Safepay signs the raw request body with the endpoint's shared secret using
 * HMAC-SHA512 and sends the hex digest in the `X-SFPY-SIGNATURE` header. The
 * comparison is constant-time so a caller cannot learn the digest by timing.
 *
 * @see https://github.com/getsafepay/sfpy-php (WebhookSignature)
 */
final class SafepaySignature
{
    public const HEADER = 'X-SFPY-SIGNATURE';

    public static function verify(string $payload, ?string $signature, ?string $secret): bool
    {
        if ($signature === null || $signature === '' || $secret === null || $secret === '') {
            return false;
        }

        return hash_equals(self::sign($payload, $secret), trim($signature));
    }

    public static function sign(string $payload, string $secret): string
    {
        return hash_hmac('sha512', $payload, $secret);
    }
}
