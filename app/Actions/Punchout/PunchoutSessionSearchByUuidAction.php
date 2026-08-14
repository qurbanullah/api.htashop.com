<?php

namespace App\Actions\Punchout;

use App\Models\PunchoutSession;

class PunchoutSessionSearchByUuidAction
{
    public function handle(string $uuid): PunchoutSession
    {
        return PunchoutSession::query()
            ->with('tenant')
            ->where('uuid', $uuid)
            ->firstOrFail();
    }
}
