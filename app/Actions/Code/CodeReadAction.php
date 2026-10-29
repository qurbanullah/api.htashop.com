<?php

namespace App\Actions\Code;

use App\Models\Code;
use App\Models\Product;
use App\Models\Variant;
use Illuminate\Pagination\LengthAwarePaginator;

class CodeReadAction
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Code>
     */
    public function handle(array $filters = []): LengthAwarePaginator
    {
        $resolved = $this->resolveCodeable(data_get($filters, 'codeable_type'), data_get($filters, 'codeable_uuid'));

        return Code::query()
            ->with(['tenant', 'organization', 'codeable'])
            ->when(data_get($filters, 'tenant_id'), fn ($query, $tenantId) => $query->where('tenant_id', $tenantId))
            ->when(data_get($filters, 'organization_id'), fn ($query, $organizationId) => $query->where('organization_id', $organizationId))
            ->when(data_get($filters, 'tenant_ids'), fn ($query, $tenantIds) => $query->whereIn('tenant_id', (array) $tenantIds))
            ->when(data_get($filters, 'organization_ids'), function ($query, $organizationIds) use ($filters) {
                $organizationIds = array_map('intval', (array) $organizationIds);

                $query->where(function ($scope) use ($organizationIds, $filters) {
                    $scope->whereIn('organization_id', $organizationIds);

                    // Tenant-level codes have no organization and are visible to
                    // every member of that tenant, matching CodePolicy::view().
                    if (data_get($filters, 'include_tenant_level')) {
                        $scope->orWhereNull('organization_id');
                    }
                });
            })
            ->when(data_get($filters, 'type'), fn ($query, $type) => $query->where('type', $type))
            ->when($resolved, fn ($query) => $query->where('codeable_type', $resolved['type'])->where('codeable_id', $resolved['id']))
            ->when(data_get($filters, 'search'), function ($query, $search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('value', 'like', '%'.$search.'%')
                        ->orWhere('normalized', 'like', '%'.mb_strtoupper($search).'%');
                });
            })
            ->orderByDesc('is_primary')
            ->orderBy('type')
            ->paginate((int) data_get($filters, 'per_page', 15));
    }

    private function resolveCodeable(?string $type, ?string $uuid): ?array
    {
        if (! $type || ! $uuid) {
            return null;
        }

        $model = match ($type) {
            'product' => Product::query()->where('uuid', $uuid)->first(),
            'variant' => Variant::query()->where('uuid', $uuid)->first(),
            default => null,
        };

        if (! $model) {
            return null;
        }

        return ['type' => $model::class, 'id' => $model->id];
    }
}
