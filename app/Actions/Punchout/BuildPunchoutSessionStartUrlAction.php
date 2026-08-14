<?php

namespace App\Actions\Punchout;

use App\Models\PunchoutSession;
use App\Models\Tenant;

class BuildPunchoutSessionStartUrlAction
{
    public function handle(Tenant $tenant, PunchoutSession $session): string
    {
        return rtrim((string) config('app.url', 'http://localhost'), '/')
            . '/api/v1/punchout/'
            . $tenant->uuid
            . '/start?session='
            . urlencode($session->uuid)
            . '&token='
            . urlencode($session->token)
            . '&protocol='
            . urlencode($session->protocol);
    }
}
