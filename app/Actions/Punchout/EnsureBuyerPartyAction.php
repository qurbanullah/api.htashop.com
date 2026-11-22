<?php

namespace App\Actions\Punchout;

use App\Models\BuyerParty;
use App\Models\PunchoutBuyer;

/**
 * Guarantees that a tenant's buyer relationship has a party behind it.
 *
 * When the buyer is identified by a network id (Ariba ANID / DUNS / customer
 * key) the party is reused, so two tenants onboarding the same buyer share one
 * item master. Otherwise a private party is created and can be linked later by
 * a platform administrator.
 */
class EnsureBuyerPartyAction
{
    public function handle(PunchoutBuyer $buyer): BuyerParty
    {
        if ($buyer->buyer_party_id !== null) {
            $existing = BuyerParty::query()->find($buyer->buyer_party_id);

            if ($existing !== null) {
                return $existing;
            }
        }

        $party = $this->partyForNetworkId($buyer) ?? $this->createPrivateParty($buyer);

        $buyer->forceFill(['buyer_party_id' => $party->id])->saveQuietly();

        return $party;
    }

    private function partyForNetworkId(PunchoutBuyer $buyer): ?BuyerParty
    {
        $networkId = trim((string) (
            data_get($buyer->settings, 'network_id')
            ?: data_get($buyer->settings, 'ariba_network_id')
            ?: data_get($buyer->settings, 'buyer_network_id')
        ));

        if ($networkId === '') {
            return null;
        }

        return BuyerParty::query()->firstOrCreate(
            ['network_id' => $networkId],
            ['name' => $buyer->name],
        );
    }

    private function createPrivateParty(PunchoutBuyer $buyer): BuyerParty
    {
        return BuyerParty::query()->create([
            'name' => $buyer->name,
            'metadata' => [
                'private' => true,
                'tenant_id' => $buyer->tenant_id,
                'buyer_uuid' => $buyer->uuid,
            ],
        ]);
    }
}
