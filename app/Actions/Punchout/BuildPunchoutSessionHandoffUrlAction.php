<?php

namespace App\Actions\Punchout;

use App\Models\PunchoutSession;
use App\Models\Tenant;

class BuildPunchoutSessionHandoffUrlAction
{
    public function handle(Tenant $tenant, PunchoutSession $session, array $configuration = []): ?string
    {
        $baseUrl = trim((string) data_get(
            $configuration,
            'catalog_url',
            data_get($configuration, 'start_page_url', '')
        ));

        if ($baseUrl === '') {
            return null;
        }

        $separator = str_contains($baseUrl, '?') ? '&' : '?';

        return $baseUrl
            . $separator
            . http_build_query([
                'tenant' => $tenant->uuid,
                'session' => $session->uuid,
                'token' => $session->token,
                'protocol' => $session->protocol,
            ]);
    }
}
