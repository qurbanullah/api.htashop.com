<?php

namespace App\Actions\Eula;

use App\Models\Eula;

class DeleteEulaAction
{
    /**
     * Execute the action to soft delete a EULA.
     */
    public function execute(Eula $eula): bool
    {
        return $eula->delete();
    }
}
