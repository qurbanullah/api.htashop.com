<?php

namespace App\Actions\Assignment;

use App\Models\Assignment;
use App\Models\Product;
use App\Models\Variant;
use Illuminate\Pagination\LengthAwarePaginator;

class AssignmentReadAction
{
    public function handle(array $filters = []): LengthAwarePaginator
    {
        $resolved = $this->resolveAssignable(data_get($filters, 'assignable_type'), data_get($filters, 'assignable_uuid'));

        return Assignment::query()
            ->with(['tenant', 'organization', 'assignable'])
            ->when(data_get($filters, 'tenant_id'), fn ($query, $tenantId) => $query->where('tenant_id', $tenantId))
            ->when(data_get($filters, 'organization_id'), fn ($query, $organizationId) => $query->where('organization_id', $organizationId))
            ->when(data_get($filters, 'role'), fn ($query, $role) => $query->where('role', $role))
            ->when(!is_null(data_get($filters, 'is_primary')), fn ($query) => $query->where('is_primary', filter_var(data_get($filters, 'is_primary'), FILTER_VALIDATE_BOOL)))
            ->when($resolved, fn ($query) => $query->where('assignable_type', $resolved['type'])->where('assignable_id', $resolved['id']))
            ->when(data_get($filters, 'search'), function ($query, $search) {
                $query->where('role', 'like', '%' . $search . '%');
            })
            ->orderByDesc('is_primary')
            ->orderBy('role')
            ->paginate((int) data_get($filters, 'per_page', 15));
    }

    private function resolveAssignable(?string $type, ?string $uuid): ?array
    {
        if (!$type || !$uuid) {
            return null;
        }

        $model = match ($type) {
            'product' => Product::query()->where('uuid', $uuid)->first(),
            'variant' => Variant::query()->where('uuid', $uuid)->first(),
            default => null,
        };

        if (!$model) {
            return null;
        }

        return ['type' => $model::class, 'id' => $model->id];
    }
}
