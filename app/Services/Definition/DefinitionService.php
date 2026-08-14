<?php

namespace App\Services\Definition;

use App\Models\Definition;
use Illuminate\Pagination\LengthAwarePaginator;

class DefinitionService
{
    public function read(array $filters = []): LengthAwarePaginator
    {
        return Definition::query()
            ->with(['options', 'targets'])
            ->when(data_get($filters, 'kind'), fn ($q, $kind) => $q->where('kind', $kind))
            ->when(data_get($filters, 'target_type'), fn ($q, $type) =>
                $q->whereHas('targets', fn ($tq) => $tq->where('target_type', $type))
            )
            ->when(data_get($filters, 'group_name'), fn ($q, $g) => $q->where('group_name', $g))
            ->when(data_get($filters, 'is_active'), fn ($q) => $q->where('is_active', true))
            ->where('is_active', true)
            ->orderBy('group_name')
            ->orderBy('name')
            ->paginate((int) data_get($filters, 'per_page', 200));
    }

    public function searchByUuid(string $uuid): Definition
    {
        return Definition::where('uuid', $uuid)->with(['options', 'targets'])->firstOrFail();
    }

    public function create(array $data): Definition
    {
        return Definition::create($data);
    }

    public function update(Definition $definition, array $data): Definition
    {
        $definition->update($data);
        return $definition->fresh();
    }

    public function delete(Definition $definition): bool
    {
        return $definition->delete() ?? false;
    }
}
