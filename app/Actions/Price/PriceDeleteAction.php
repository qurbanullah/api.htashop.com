<?php

namespace App\Actions\Price;

use App\Models\Price;

class PriceDeleteAction
{
    public function handle(Price $price): bool
    {
        return (bool) $price->delete();
    }
}
