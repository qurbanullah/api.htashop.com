<?php

namespace App\Support\Punchout\Data;

class PunchoutOrderRequestData
{
    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @param  array<string, mixed>  $shipTo
     * @param  array<string, mixed>  $payload
     * @param  array<string, string|null>  $credentials
     */
    public function __construct(
        public readonly string $protocol,
        public readonly string $payloadId,
        public readonly ?string $orderId,
        public readonly ?string $orderDate,
        public readonly ?string $currency,
        public readonly ?float $declaredTotal,
        public readonly array $lines,
        public readonly array $shipTo,
        public readonly array $credentials,
        public readonly array $payload,
    ) {}
}
