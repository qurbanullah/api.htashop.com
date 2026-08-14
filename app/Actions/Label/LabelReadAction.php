<?php

namespace App\Actions\Label;

use App\Models\Label;
use Illuminate\Pagination\LengthAwarePaginator;

class LabelReadAction
{
    public function handle(array $filters = []): LengthAwarePaginator
    {
        return Label::query()
            ->when(array_key_exists('tenant_id', $filters) && !is_null($filters['tenant_id']), function ($query) use ($filters) {
                $tenantId = $filters['tenant_id'];
                $query->where(function ($inner) use ($tenantId) {
                    $inner->where('tenant_id', $tenantId)->orWhereNull('tenant_id');
                });
            })
            ->when(array_key_exists('tenant_id', $filters) && is_null($filters['tenant_id']), fn ($query) => $query->whereNull('tenant_id'))
            ->when(data_get($filters, 'search'), function ($query, $search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('slug', 'like', '%' . $search . '%')
                        ->orWhere('name', 'like', '%' . $search . '%')
                        ->orWhere('summary', 'like', '%' . $search . '%')
                        ->orWhere('description', 'like', '%' . $search . '%');
                });
            })
            ->when(!is_null(data_get($filters, 'is_active')), fn ($query) => $query->where('is_active', (bool) data_get($filters, 'is_active')))
            ->orderByRaw('CASE WHEN sorting IS NULL THEN 1 ELSE 0 END')
            ->orderBy('sorting')
            ->orderBy('name')
            ->paginate((int) data_get($filters, 'per_page', 15));
    }
}
