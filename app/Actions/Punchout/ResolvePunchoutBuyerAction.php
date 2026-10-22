<?php

namespace App\Actions\Punchout;

use App\Models\PunchoutBuyer;
use App\Models\Tenant;

class ResolvePunchoutBuyerAction
{
    /**
     * Match a registered buyer from the credentials in a setup (or inbound
     * order) request. Buyer records are scoped to a tenant and, when set, a
     * protocol. Identities left null act as wildcards; the shared secret is
     * always verified so an identity alone cannot claim a buyer.
     *
     * @param  array{sender_identity?: ?string, shared_secret?: ?string, from_identity?: ?string, to_identity?: ?string, username?: ?string, password?: ?string}  $credentials
     */
    public function handle(Tenant $tenant, string $protocol, array $credentials): ?PunchoutBuyer
    {
        $candidates = PunchoutBuyer::query()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->where(function ($query) use ($protocol): void {
                $query->whereNull('protocol')->orWhere('protocol', $protocol);
            })
            ->orderByDesc('id')
            ->get();

        foreach ($candidates as $buyer) {
            if (! $this->matches($buyer, $protocol, $credentials)) {
                continue;
            }

            $buyer->forceFill(['last_seen_at' => now()])->save();

            return $buyer;
        }

        return null;
    }

    public function hasActiveBuyers(Tenant $tenant, ?string $protocol = null): bool
    {
        return PunchoutBuyer::query()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->when($protocol, fn ($query) => $query->where(function ($query) use ($protocol): void {
                $query->whereNull('protocol')->orWhere('protocol', $protocol);
            }))
            ->exists();
    }

    private function matches(PunchoutBuyer $buyer, string $protocol, array $credentials): bool
    {
        if ($protocol === 'oci') {
            if (! $this->identityMatches($buyer->oci_username, data_get($credentials, 'username'))) {
                return false;
            }

            return $buyer->matchesOciPassword(data_get($credentials, 'password'));
        }

        if (! $this->identityMatches($buyer->sender_identity, data_get($credentials, 'sender_identity'))) {
            return false;
        }

        if (! $this->identityMatches($buyer->from_identity, data_get($credentials, 'from_identity'))) {
            return false;
        }

        if (! $this->identityMatches($buyer->to_identity, data_get($credentials, 'to_identity'))) {
            return false;
        }

        return $buyer->matchesSharedSecret(data_get($credentials, 'shared_secret'));
    }

    private function identityMatches(?string $expected, mixed $actual): bool
    {
        $expected = trim((string) $expected);

        if ($expected === '') {
            return true;
        }

        return hash_equals($expected, trim((string) $actual));
    }
}
