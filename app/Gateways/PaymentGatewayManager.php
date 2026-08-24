<?php

namespace App\Gateways;

use App\Enums\PaymentMethod;
use InvalidArgumentException;

/**
 * Resolves the gateway implementation for a payment method.
 * New gateways register themselves here (single source of truth).
 */
class PaymentGatewayManager
{
    public function __construct(
        protected CodGateway $codGateway,
    ) {
    }

    public function gateway(string $method): PaymentGateway
    {
        return match ($method) {
            PaymentMethod::COD => $this->codGateway,
            // JazzCash / EasyPaisa / UPaisa / Safepay will register here.
            default => throw new InvalidArgumentException("Unsupported payment method: {$method}"),
        };
    }
}
