<?php

namespace App\Actions\Warehouse;

use App\Models\Warehouse;

class WarehouseDeleteAction
{
    public function handle(Warehouse $warehouse): bool
    {
        return (bool) $warehouse->delete();
    }
}
