<?php

namespace App\Services\Definition;

use App\Models\Definition;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

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
            ->when(data_get($filters, 'section_name'), fn ($q, $s) => $q->where('section_name', $s))
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
        $data['code'] = $this->resolveCode(
            data_get($data, 'code'),
            data_get($data, 'name'),
            (int) data_get($data, 'tenant_id'),
        );

        return Definition::create($data);
    }

    public function update(Definition $definition, array $data): Definition
    {
        $data['code'] = $this->resolveCode(
            data_get($data, 'code'),
            data_get($data, 'name', $definition->name),
            (int) data_get($data, 'tenant_id', $definition->tenant_id),
            $definition->id,
        );

        $definition->update($data);

        return $definition->fresh();
    }

    public function delete(Definition $definition): bool
    {
        return $definition->delete() ?? false;
    }

    /**
     * Derive a unique code from the provided value or the name.
     * Codes are unique per tenant (definitions.tenant_id + code).
     */
    private function resolveCode(?string $code, ?string $name, int $tenantId, ?int $ignoreId = null): string
    {
        $candidate = Str::slug($code ?: $name ?: '', '_') ?: 'code';
        $base = $candidate;
        $suffix = 1;

        while (Definition::query()
            ->where('tenant_id', $tenantId)
            ->where('code', $candidate)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists()) {
            $candidate = $base . '_' . $suffix;
            $suffix++;
        }

        return $candidate;
    }
}
