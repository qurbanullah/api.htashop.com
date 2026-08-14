<?php

namespace App\Actions\Specifications;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class GetSpecificationsAction
{
    public function execute(Model $specifiable): Collection
    {
        return $specifiable->specifications()
            ->orderBy('group')
            ->orderBy('sort_order')
            ->get();
    }
}
