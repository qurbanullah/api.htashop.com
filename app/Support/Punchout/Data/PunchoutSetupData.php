<?php

namespace App\Support\Punchout\Data;

class PunchoutSetupData
{
    public function __construct(
        public readonly string $protocol,
        public readonly string $operation,
        public readonly string $returnUrl,
        public readonly ?string $buyerCookie,
        public readonly array $payload = [],
    ) {
    }
}