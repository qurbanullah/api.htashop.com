<?php

namespace App\Actions\Label;

use App\Models\Label;

class LabelSearchByUuidAction
{
    public function handle(string $uuid): Label
    {
        return Label::query()->where('uuid', $uuid)->firstOrFail();
    }
}
