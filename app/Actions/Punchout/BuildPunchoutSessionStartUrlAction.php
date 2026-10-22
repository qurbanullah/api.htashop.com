<?php

namespace App\Actions\Punchout;

use App\Models\PunchoutSession;
use App\Models\Tenant;

class BuildPunchoutSessionStartUrlAction
{
    public function handle(Tenant $tenant, PunchoutSession $session): string
    {
        // Build the path from the named route (so a version/prefix change cannot
        // drift) but take the origin from config: the API may be behind a proxy
        // whose request host is not the public host.
        $path = route('punchout.start', [
            'tenantIdentifier' => $tenant->uuid,
            'session' => $session->uuid,
            'token' => $session->token,
            'protocol' => $session->protocol,
        ], false);

        return rtrim((string) config('app.url', 'http://localhost'), '/').$path;
    }
}
