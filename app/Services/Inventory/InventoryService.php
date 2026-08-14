<?php

namespace App\Services\Inventory;

use App\Actions\Inventory\InventoryDeleteAction;
use App\Actions\Inventory\InventoryReadAction;
use App\Actions\Inventory\InventoryUpdateAction;
use App\Actions\Inventory\InventoryUpsertAction;
use App\Models\Inventory;

class InventoryService
{
    public function __construct(
        protected InventoryUpsertAction $upsertAction,
        protected InventoryReadAction $readAction,
        protected InventoryUpdateAction $updateAction,
        protected InventoryDeleteAction $deleteAction,
    ) {
    }

    public function read(array $filters = []): \Illuminate\Database\Eloquent\Collection
    {
        $user = auth()->user();
        if (!$user?->hasRole(['super-admin', 'admin'])) {
            $membership = $user?->memberships()->where('is_active', true)->first();
            $filters['tenant_id'] = $membership?->tenant_id;
        }

        return $this->readAction->handle($filters);
    }

    public function upsert(array $data): Inventory
    {
        // Auto-resolve tenant
        if (empty($data['tenant_id'])) {
            $membership = auth()->user()?->memberships()->where('is_active', true)->first();
            if ($membership) {
                $data['tenant_id'] = $membership->tenant_id;
            }
        }

        return $this->upsertAction->handle($data);
    }

    public function update(Inventory $inventory, array $data): Inventory
    {
        return $this->updateAction->handle($inventory, $data);
    }

    public function delete(Inventory $inventory): bool
    {
        return $this->deleteAction->handle($inventory);
    }
}
