<?php

namespace App\Actions\Eula;

use App\Models\Consent;
use App\Models\Eula;

class CheckConsentAction
{
    /**
     * Execute the action to check if user has consented to a specific EULA.
     */
    public function execute(
        int $userId,
        int $eulaId,
        ?string $consentableType = null,
        ?int $consentableId = null
    ): bool {
        $query = Consent::where('user_id', $userId)
            ->where('eula_id', $eulaId);

        if ($consentableType && $consentableId) {
            $query->where('consentable_type', $consentableType)
                  ->where('consentable_id', $consentableId);
        }

        return $query->exists();
    }

    /**
     * Check if user has consented to the latest active EULA.
     */
    public function hasConsentedToLatest(
        int $userId,
        ?int $softwareId = null,
        ?int $versionId = null
    ): bool {
        $latestEula = Eula::active()
            ->when($softwareId, function ($query) use ($softwareId, $versionId) {
                $query->forSoftware($softwareId, $versionId);
            })
            ->orderBy('effective_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$latestEula) {
            return true; // No active EULA, so no consent required
        }

        return $this->execute($userId, $latestEula->id);
    }
}
