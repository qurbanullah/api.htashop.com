<?php

namespace App\Actions\Unit;

use App\Models\Unit;

class UnitDeleteAction
{
    public function handle(Unit $unit): bool
    {
        return (bool) $unit->delete();
    }
}
