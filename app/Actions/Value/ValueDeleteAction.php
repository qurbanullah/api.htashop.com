<?php

namespace App\Actions\Value;

use App\Models\Value;

class ValueDeleteAction
{
    public function handle(Value $value): bool
    {
        return (bool) $value->delete();
    }
}
