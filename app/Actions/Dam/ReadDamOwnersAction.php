<?php

namespace App\Actions\Dam;

use App\Models\Organization;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\Variant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ReadDamOwnersAction
{
    public function handle(array $filters = []): Collection
    {
        $type = strtolower((string) data_get($filters, 'type', ''));
        $query = trim((string) data_get($filters, 'query', ''));
        $limit = max(1, min((int) data_get($filters, 'limit', 10), 25));
        $tenantId = data_get($filters, 'tenant_id');
        $organizationId = data_get($filters, 'organization_id');

        [$modelClass, $label, $searchColumns] = match ($type) {
            'product' => [Product::class, 'product', ['name', 'slug', 'uuid']],
            'variant' => [Variant::class, 'variant', ['name', 'slug', 'uuid']],
            'tenant' => [Tenant::class, 'tenant', ['name', 'slug', 'domain', 'uuid']],
            'organization' => [Organization::class, 'organization', ['name', 'slug', 'code', 'email', 'uuid']],
            default => throw ValidationException::withMessages([
                'type' => ['Unsupported DAM owner type. Supported types: product, variant, tenant, organization.'],
            ]),
        };

        return $modelClass::query()
            ->when($type === 'tenant' && $tenantId, fn (Builder $builder) => $builder->whereKey($tenantId))
            ->when($type === 'organization' && $tenantId, fn (Builder $builder) => $builder->where('tenant_id', $tenantId))
            ->when($type === 'organization' && $organizationId, fn (Builder $builder) => $builder->whereKey($organizationId))
            ->when($type === 'product' && $tenantId, fn (Builder $builder) => $builder->where('tenant_id', $tenantId))
            ->when($type === 'product' && $organizationId, fn (Builder $builder) => $builder->where('organization_id', $organizationId))
            ->when($type === 'variant' && $tenantId, fn (Builder $builder) => $builder->whereHas('product', fn (Builder $productBuilder) => $productBuilder->where('tenant_id', $tenantId)))
            ->when($type === 'variant' && $organizationId, fn (Builder $builder) => $builder->whereHas('product', fn (Builder $productBuilder) => $productBuilder->where('organization_id', $organizationId)))
            ->when($query !== '', function (Builder $builder) use ($query, $searchColumns) {
                $builder->where(function (Builder $innerBuilder) use ($query, $searchColumns) {
                    foreach ($searchColumns as $index => $column) {
                        if ($index === 0) {
                            $innerBuilder->where($column, 'like', '%' . $query . '%');
                            continue;
                        }

                        $innerBuilder->orWhere($column, 'like', '%' . $query . '%');
                    }
                });
            })
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(function ($owner) use ($label) {
                return [
                    'type' => $label,
                    'model' => get_class($owner),
                    'id' => $owner->id,
                    'uuid' => $owner->uuid,
                    'name' => $owner->name,
                    'slug' => $owner->slug,
                ];
            })
            ->values();
    }
}
