<?php

declare(strict_types=1);

namespace App\Support\Observability;

/**
 * One-way IP pseudonymisation for logs and audit rows.
 *
 * The raw address is used only for the duration of the request; what is
 * persisted is an HMAC bound to the application key, so the same address can be
 * correlated within this installation but cannot be reversed by reading the
 * database or an exported NDJSON file.
 */
final class IpHasher
{
    public static function hash(?string $ip): ?string
    {
        $ip = trim((string) $ip);

        if ($ip === '') {
            return null;
        }

        $key = (string) config('app.key', '');

        if ($key === '') {
            return null;
        }

        return hash_hmac('sha256', $ip, $key);
    }
}
