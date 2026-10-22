<?php

namespace App\Actions\Punchout;

use App\Models\PunchoutSession;

class PunchoutSessionSearchByUuidAction
{
    public function handle(string $uuid): PunchoutSession
    {
        return PunchoutSession::query()
            ->with(['tenant', 'buyer'])
            ->where('uuid', $uuid)
            ->firstOrFail();
    }
}
