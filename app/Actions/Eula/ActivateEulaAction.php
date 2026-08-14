<?php

namespace App\Actions\Eula;

use App\Enums\EulaStatus;
use App\Models\Eula;

class ActivateEulaAction
{
    /**
     * Execute the action to activate a EULA.
     * Deactivates other active EULAs for the same software/version if specified.
     */
    public function execute(Eula $eula, bool $deactivateOthers = true): Eula
    {
        // Deactivate other active EULAs for same software/version combination
        if ($deactivateOthers && $eula->software_id) {
            Eula::active()
                ->where('software_id', $eula->software_id)
                ->where('id', '!=', $eula->id)
                ->when($eula->version_id, function ($query) use ($eula) {
                    $query->where('version_id', $eula->version_id);
                })
                ->update(['status' => EulaStatus::INACTIVE]);
        }

        // Activate this EULA
        $eula->update([
            'status' => EulaStatus::ACTIVE,
            'effective_date' => $eula->effective_date ?? now(),
        ]);

        return $eula->fresh();
    }
}
