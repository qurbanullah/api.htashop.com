<?php

namespace App\Actions\Definition;

use App\Models\Definition;

class DefinitionSearchByUuidAction
{
    public function handle(string $uuid): Definition
    {
        return Definition::query()
            ->with(['parent', 'measurement', 'unit.measurement', 'labels', 'targets'])
            ->where('uuid', $uuid)
            ->firstOrFail();
    }
}
