<?php

namespace App\Actions\Definition;

use App\Models\Definition;

class DefinitionDeleteAction
{
    public function handle(Definition $definition): bool
    {
        return (bool) $definition->delete();
    }
}
