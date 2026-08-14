<?php

namespace App\Actions\Eula;

use App\Models\Consent;
use Illuminate\Support\Arr;

class RecordConsentAction
{
    /**
     * Execute the action to record user consent for a EULA.
     */
    public function execute(array $data): Consent
    {
        return Consent::create([
            'user_id' => $data['user_id'],
            'eula_id' => $data['eula_id'],
            'consentable_type' => Arr::get($data, 'consentable_type'),
            'consentable_id' => Arr::get($data, 'consentable_id'),
            'ip_address' => Arr::get($data, 'ip_address'),
            'user_agent' => Arr::get($data, 'user_agent'),
            'metadata' => Arr::get($data, 'metadata', []),
            'accepted_at' => now(),
        ]);
    }
}
