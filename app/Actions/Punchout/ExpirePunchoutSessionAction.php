<?php

namespace App\Actions\Punchout;

use App\Models\PunchoutSession;

class ExpirePunchoutSessionAction
{
    public function handle(PunchoutSession $session): PunchoutSession
    {
        if ($session->status === 'expired') {
            return $session;
        }

        $session->status = 'expired';
        $session->last_activity_at = now();
        $session->save();

        return $session->fresh();
    }
}
