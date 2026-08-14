<?php

namespace App\Actions\Label;

use App\Models\Label;

class LabelDeleteAction
{
    public function handle(Label $label): bool
    {
        return (bool) $label->delete();
    }
}
