<?php

namespace App\Actions\Punchout;

use App\Models\PunchoutBuyer;
use App\Models\PunchoutSession;
use App\Models\Tenant;
use App\Support\Punchout\Data\PunchoutSetupData;

class CreatePunchoutSessionAction
{
    public function handle(
        Tenant $tenant,
        PunchoutSetupData $setupData,
        array $configuration = [],
        ?PunchoutBuyer $buyer = null,
    ): PunchoutSession {
        $ttlMinutes = (int) data_get($configuration, 'session_ttl_minutes', config('punchout.session_ttl_minutes', 30));
        $expiresAt = now()->addMinutes($ttlMinutes);
        $idleExpiresAt = now()->addMinutes(max(1, (int) config('punchout.session_idle_minutes', 15)));

        if ($idleExpiresAt->greaterThan($expiresAt)) {
            $idleExpiresAt = $expiresAt;
        }

        // Strip OCI credentials from the persisted payload — they are validated during setup
        // and must not be stored at rest.
        $payload = $setupData->payload;
        if ($setupData->protocol === 'oci') {
            unset($payload['username'], $payload['password']);
        }

        return PunchoutSession::query()->create([
            'tenant_id' => $tenant->id,
            'buyer_id' => $buyer?->id,
            'protocol' => $setupData->protocol,
            'status' => 'pending',
            'buyer_cookie' => $setupData->buyerCookie,
            'return_url' => $setupData->returnUrl,
            'setup_payload' => $payload,
            'expires_at' => $expiresAt,
            'idle_expires_at' => $idleExpiresAt,
            'last_activity_at' => now(),
        ]);
    }
}
