<?php

namespace App\Actions\Measurement;

use App\Models\Measurement;
use Illuminate\Pagination\LengthAwarePaginator;

class MeasurementReadAction
{
    public function handle(array $filters = []): LengthAwarePaginator
    {
        return Measurement::query()
            ->when(data_get($filters, 'tenant_id'), fn ($query, $tenantId) => $query->where('tenant_id', $tenantId))
            ->when(!is_null(data_get($filters, 'is_active')), fn ($query) => $query->where('is_active', (bool) data_get($filters, 'is_active')))
            ->when(data_get($filters, 'search'), function ($query, $search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', '%' . $search . '%')
                        ->orWhere('code', 'like', '%' . $search . '%')
                        ->orWhere('description', 'like', '%' . $search . '%');
                });
            })
            ->orderBy('name')
            ->paginate((int) data_get($filters, 'per_page', 15));
    }
}
