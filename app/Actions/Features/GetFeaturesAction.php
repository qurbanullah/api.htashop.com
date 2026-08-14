<?php

namespace App\Actions\Features;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class GetFeaturesAction
{
    public function execute(Model $featurable): Collection
    {
        return $featurable->features()->orderBy('sort_order')->get();
    }
}
