<?php

namespace App\Actions\Eula;

use App\Models\Eula;

class GetActiveEulaAction
{
    /**
     * Execute the action to get the active EULA for specific software/version.
     */
    public function execute(?int $softwareId = null, ?int $versionId = null): ?Eula
    {
        $query = Eula::active();

        if ($softwareId) {
            $query->forSoftware($softwareId, $versionId);
        }

        return $query->orderBy('effective_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->first();
    }
}
