<?php

namespace App\Support\Punchout\Data;

class PunchoutResponseData
{
    public function __construct(
        public readonly string $content,
        public readonly string $contentType,
        public readonly int $status = 200,
    ) {
    }
}