<?php

namespace App\Support\Punchout\Data;

use App\Models\PunchoutSession;

class PunchoutStartResultData
{
    public function __construct(
        public readonly PunchoutSession $session,
        public readonly ?string $redirectUrl = null,
    ) {
    }
}
