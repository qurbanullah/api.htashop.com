<?php

namespace App\Actions\Punchout;

use App\Models\PunchoutSession;
use Carbon\CarbonInterface;

class TouchPunchoutSessionAction
{
    public function handle(PunchoutSession $session): PunchoutSession
    {
        $session->update([
            'last_activity_at' => now(),
            'idle_expires_at' => $this->idleExpiry($session),
        ]);

        return $session->fresh();
    }

    public function idleExpiry(PunchoutSession $session): CarbonInterface
    {
        $idle = now()->addMinutes(max(1, (int) config('punchout.session_idle_minutes', 15)));

        if ($session->expires_at !== null && $idle->greaterThan($session->expires_at)) {
            return $session->expires_at;
        }

        return $idle;
    }
}
