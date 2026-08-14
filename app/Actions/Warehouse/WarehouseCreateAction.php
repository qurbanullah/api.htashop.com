<?php

namespace App\Actions\Warehouse;

use App\Models\Warehouse;

class WarehouseCreateAction
{
    public function handle(array $data): Warehouse
    {
        return Warehouse::create($data);
    }
}
