<?php

namespace App\Actions\Unit;

use App\Models\Unit;
use Illuminate\Pagination\LengthAwarePaginator;

class UnitReadAction
{
    public function handle(array $filters = []): LengthAwarePaginator
    {
        return Unit::query()
            ->with('measurement')
            ->when(data_get($filters, 'tenant_id'), fn ($query, $tenantId) => $query->where('tenant_id', $tenantId))
            ->when(data_get($filters, 'measurement_id'), fn ($query, $measurementId) => $query->where('measurement_id', $measurementId))
            ->when(!is_null(data_get($filters, 'is_active')), fn ($query) => $query->where('is_active', (bool) data_get($filters, 'is_active')))
            ->when(data_get($filters, 'search'), function ($query, $search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', '%' . $search . '%')
                        ->orWhere('code', 'like', '%' . $search . '%')
                        ->orWhere('symbol', 'like', '%' . $search . '%');
                });
            })
            ->orderBy('name')
            ->paginate((int) data_get($filters, 'per_page', 15));
    }
}
