<?php

namespace App\Gateways;

/**
 * Result of initializing a payment with a gateway.
 */
class PaymentInitResult
{
    public function __construct(
        public readonly bool $requiresRedirect,
        public readonly ?string $redirectUrl = null,
        public readonly ?string $html = null,
        public readonly array $payload = [],
    ) {
    }
}
