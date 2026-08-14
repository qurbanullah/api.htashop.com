<?php

namespace App\Support\Punchout\Data;

class PunchoutCartData
{
    public function __construct(
        public readonly string $protocol,
        public readonly string $operation,
        public readonly string $sessionUuid,
        public readonly string $sessionToken,
        public readonly string $returnUrl,
        public readonly ?string $buyerCookie,
        public readonly array $items,
        public readonly array $payload = [],
    ) {
    }
}
