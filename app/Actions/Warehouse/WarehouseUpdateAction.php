<?php

namespace App\Actions\Warehouse;

use App\Models\Warehouse;

class WarehouseUpdateAction
{
    public function handle(Warehouse $warehouse, array $data): Warehouse
    {
        $warehouse->update($data);
        return $warehouse->fresh();
    }
}
