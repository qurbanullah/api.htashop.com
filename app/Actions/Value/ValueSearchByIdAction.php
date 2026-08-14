<?php

namespace App\Actions\Value;

use App\Models\Value;

class ValueSearchByIdAction
{
    public function handle(int $id): Value
    {
        return Value::query()->with(['tenant', 'definition', 'option', 'unit', 'valuable'])->findOrFail($id);
    }
}
