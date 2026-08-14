<?php

namespace App\Actions\Unit;

use App\Models\Unit;

class UnitSearchByUuidAction
{
    public function handle(string $uuid): Unit
    {
        return Unit::query()->with('measurement')->where('uuid', $uuid)->firstOrFail();
    }
}
