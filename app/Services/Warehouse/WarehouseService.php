<?php

namespace App\Services\Warehouse;

use App\Actions\Warehouse\WarehouseCreateAction;
use App\Actions\Warehouse\WarehouseDeleteAction;
use App\Actions\Warehouse\WarehouseReadAction;
use App\Actions\Warehouse\WarehouseSearchByUuidAction;
use App\Actions\Warehouse\WarehouseUpdateAction;
use App\Models\Warehouse;
use Illuminate\Pagination\LengthAwarePaginator;

class WarehouseService
{
    public function __construct(
        protected WarehouseCreateAction $createAction,
        protected WarehouseReadAction $readAction,
        protected WarehouseSearchByUuidAction $searchByUuidAction,
        protected WarehouseUpdateAction $updateAction,
        protected WarehouseDeleteAction $deleteAction,
    ) {
    }

    public function read(array $filters = []): LengthAwarePaginator
    {
        $user = auth()->user();

        // Non-admin users are scoped to their active membership tenant
        if (!$user?->hasRole(['super-admin', 'admin'])) {
            $membership = $user?->memberships()->where('is_active', true)->first();
            $filters['tenant_id'] = $membership?->tenant_id;
        }

        return $this->readAction->handle($filters);
    }

    public function searchByUuid(string $uuid): Warehouse
    {
        return $this->searchByUuidAction->handle($uuid);
    }

    public function create(array $data): Warehouse
    {
        // Auto-resolve tenant from user's active membership
        if (empty($data['tenant_id'])) {
            $membership = auth()->user()?->memberships()->where('is_active', true)->first();
            if ($membership) {
                $data['tenant_id'] = $membership->tenant_id;
            }
        }

        return $this->createAction->handle($data);
    }

    public function update(Warehouse $warehouse, array $data): Warehouse
    {
        return $this->updateAction->handle($warehouse, $data);
    }

    public function delete(Warehouse $warehouse): bool
    {
        return $this->deleteAction->handle($warehouse);
    }
}
