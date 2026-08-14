<?php

namespace App\Actions\Warehouse;

use App\Models\Warehouse;

class WarehouseSearchByUuidAction
{
    public function handle(string $uuid): Warehouse
    {
        return Warehouse::where('uuid', $uuid)->firstOrFail();
    }
}
