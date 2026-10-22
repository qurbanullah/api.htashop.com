<?php

namespace App\Actions\Punchout;

use App\Models\PunchoutSession;
use Illuminate\Validation\ValidationException;

class ValidatePunchoutReturnUrlAction
{
    /**
     * The cart must go back to the URL captured at setup. Comparing origin and
     * path blocks the open-redirect/exfiltration case where a caller with a
     * valid session posts the basket to an arbitrary host.
     */
    public function handle(PunchoutSession $session, ?string $postedUrl = null): void
    {
        $expected = trim((string) $session->return_url);
        $actual = trim((string) ($postedUrl ?: $session->return_url));

        if ($expected === '' || $actual === '') {
            throw ValidationException::withMessages([
                'return_url' => ['The punchout return URL is missing.'],
            ]);
        }

        $expectedParts = parse_url($expected);
        $actualParts = parse_url($actual);

        if (! $expectedParts || ! $actualParts) {
            throw ValidationException::withMessages([
                'return_url' => ['The punchout return URL is invalid.'],
            ]);
        }

        foreach (['scheme', 'host', 'port', 'path'] as $part) {
            if ($this->part($expectedParts, $part) !== $this->part($actualParts, $part)) {
                throw ValidationException::withMessages([
                    'return_url' => ['The punchout return URL does not match the setup request.'],
                ]);
            }
        }

        if (! in_array(strtolower((string) ($actualParts['scheme'] ?? '')), ['http', 'https'], true)) {
            throw ValidationException::withMessages([
                'return_url' => ['The punchout return URL must use HTTP or HTTPS.'],
            ]);
        }

        $buyer = $session->buyer;

        if ($buyer !== null && ! $buyer->allowsReturnHost((string) ($actualParts['host'] ?? ''))) {
            throw ValidationException::withMessages([
                'return_url' => ['The punchout return URL is not allowed for this buyer.'],
            ]);
        }
    }

    private function part(array $parts, string $name): string
    {
        return match ($name) {
            'scheme' => strtolower((string) ($parts['scheme'] ?? '')),
            'host' => strtolower((string) ($parts['host'] ?? '')),
            'port' => (string) ($parts['port'] ?? (($parts['scheme'] ?? '') === 'https' ? 443 : 80)),
            'path' => rtrim((string) ($parts['path'] ?? '/'), '/') ?: '/',
            default => '',
        };
    }
}
