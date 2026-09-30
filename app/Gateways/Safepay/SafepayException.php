<?php

namespace App\Gateways\Safepay;

use RuntimeException;

/**
 * Raised when Safepay rejects a request or returns an unusable response.
 *
 * Carries the HTTP status and decoded body so the caller can distinguish a
 * transient failure (retry) from a rejected one (surface to the operator).
 */
class SafepayException extends RuntimeException
{
    public function __construct(
        string $message,
        private int $status = 0,
        private array $payload = [],
    ) {
        parent::__construct($message, $status);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function payload(): array
    {
        return $this->payload;
    }
}
