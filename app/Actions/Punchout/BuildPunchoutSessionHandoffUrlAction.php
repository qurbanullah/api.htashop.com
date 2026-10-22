<?php

namespace App\Actions\Punchout;

use App\Models\PunchoutSession;
use App\Models\Tenant;

class BuildPunchoutSessionHandoffUrlAction
{
    public function handle(Tenant $tenant, PunchoutSession $session, array $configuration = [], ?string $handoffCode = null): ?string
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

        $query = [
            'tenant' => $tenant->uuid,
            'session' => $session->uuid,
            'protocol' => $session->protocol,
        ];

        // Prefer the one-time code. The legacy token is kept only when no code
        // was issued, so an existing buyer configuration still resolves.
        if ($handoffCode !== null) {
            $query['code'] = $handoffCode;
        } else {
            $query['token'] = $session->token;
        }

        return $baseUrl.$separator.http_build_query($query);
    }
}
