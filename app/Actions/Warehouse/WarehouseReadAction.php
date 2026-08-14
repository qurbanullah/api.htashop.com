<?php

namespace App\Actions\Warehouse;

use App\Models\Warehouse;
use Illuminate\Pagination\LengthAwarePaginator;

class WarehouseReadAction
{
    public function handle(array $filters = []): LengthAwarePaginator
    {
        return Warehouse::query()
            ->when(data_get($filters, 'tenant_id'), fn ($q, $id) => $q->where('tenant_id', $id))
            ->when(data_get($filters, 'search'), fn ($q, $s) => $q->where(fn ($inner) =>
                $inner->where('name', 'like', "%{$s}%")
                    ->orWhere('code', 'like', "%{$s}%")
                    ->orWhere('city', 'like', "%{$s}%")
            ))
            ->when(data_get($filters, 'is_active'), fn ($q) => $q->where('is_active', true))
            ->orderBy('name')
            ->paginate((int) data_get($filters, 'per_page', 50));
    }
}
