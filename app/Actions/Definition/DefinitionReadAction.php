<?php

namespace App\Actions\Definition;

use App\Models\Definition;
use Illuminate\Pagination\LengthAwarePaginator;

class DefinitionReadAction
{
    public function handle(array $filters = []): LengthAwarePaginator
    {
        return Definition::query()
            ->with(['parent', 'measurement', 'unit.measurement', 'labels', 'targets'])
            ->when(data_get($filters, 'tenant_id'), fn ($query, $tenantId) => $query->where('tenant_id', $tenantId))
            ->when(data_get($filters, 'kind'), fn ($query, $kind) => $query->where('kind', $kind))
            ->when(data_get($filters, 'value_type'), fn ($query, $valueType) => $query->where('value_type', $valueType))
            ->when(data_get($filters, 'group_name'), fn ($query, $groupName) => $query->where('group_name', $groupName))
            ->when(data_get($filters, 'section_name'), fn ($query, $sectionName) => $query->where('section_name', $sectionName))
            ->when(!is_null(data_get($filters, 'is_active')), fn ($query) => $query->where('is_active', (bool) data_get($filters, 'is_active')))
            ->when(data_get($filters, 'search'), function ($query, $search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', '%' . $search . '%')
                        ->orWhere('code', 'like', '%' . $search . '%')
                        ->orWhere('group_name', 'like', '%' . $search . '%')
                        ->orWhere('section_name', 'like', '%' . $search . '%');
                });
            })
            ->orderBy('group_name')
            ->orderBy('section_name')
            ->orderBy('name')
            ->paginate((int) data_get($filters, 'per_page', 15));
    }
}
