<?php

namespace App\Services\Inventory;

use App\Actions\Inventory\InventoryDeleteAction;
use App\Actions\Inventory\InventoryReadAction;
use App\Actions\Inventory\InventoryUpdateAction;
use App\Actions\Inventory\InventoryUpsertAction;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Variant;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Collection;

class InventoryService
{
    public function __construct(
        protected InventoryUpsertAction $upsertAction,
        protected InventoryReadAction $readAction,
        protected InventoryUpdateAction $updateAction,
        protected InventoryDeleteAction $deleteAction,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Inventory>
     */
    public function read(array $filters = []): Collection
    {
        $user = auth()->user();

        if (! $user?->isAdmin()) {
            abort_if($user === null, 401, 'Authentication required.');

            $allowedTenantIds = $user->activeTenantIds();
            $allowedOrganizationIds = $user->activeOrganizationIds();

            abort_if(
                $allowedOrganizationIds === [],
                403,
                'No active organization is associated with your account.'
            );

            $requestedTenantId = data_get($filters, 'tenant_id');

            if ($requestedTenantId !== null) {
                abort_unless(
                    in_array((int) $requestedTenantId, $allowedTenantIds, true),
                    403,
                    'You do not have access to this tenant.'
                );
            }

            $filters['tenant_ids'] = $requestedTenantId !== null
                ? [(int) $requestedTenantId]
                : $allowedTenantIds;

            $filters['organization_ids'] = $allowedOrganizationIds;

            unset($filters['tenant_id']);
        }

        return $this->readAction->handle($filters);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function upsert(array $data): Inventory
    {
        $stockable = $this->resolveStockable(
            data_get($data, 'stockable_type'),
            data_get($data, 'stockable_id')
        );

        abort_if($stockable === null, 422, 'The selected stockable record could not be found.');

        $this->authorizeStockable($stockable);

        // The stockable is the source of truth for the owning tenant, so a
        // caller cannot write inventory into someone else's tenant.
        $data['stockable_type'] = $stockable::class;
        $data['tenant_id'] = (int) $stockable->tenant_id;

        if (($data['warehouse_id'] ?? null) !== null) {
            $this->assertWarehouseBelongsToTenant((int) $data['warehouse_id'], (int) $data['tenant_id']);
        }

        return $this->upsertAction->handle($data, [$this->typeAlias($stockable)]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Inventory $inventory, array $data): Inventory
    {
        $this->authorizeInventory($inventory);

        if (($data['warehouse_id'] ?? null) !== null) {
            $this->assertWarehouseBelongsToTenant((int) $data['warehouse_id'], (int) $inventory->tenant_id);
        }

        return $this->updateAction->handle($inventory, $data);
    }

    public function delete(Inventory $inventory): bool
    {
        $this->authorizeInventory($inventory);

        return $this->deleteAction->handle($inventory);
    }

    private function resolveStockable(?string $type, mixed $id): Product|Variant|null
    {
        if (! $type || ! $id) {
            return null;
        }

        return match ($type) {
            'product', Product::class => Product::query()->find($id),
            'variant', Variant::class => Variant::query()->find($id),
            default => null,
        };
    }

    private function typeAlias(Product|Variant $stockable): string
    {
        return $stockable instanceof Product ? 'product' : 'variant';
    }

    private function authorizeInventory(Inventory $inventory): void
    {
        $this->authorizeStockable(
            $this->resolveStockable($inventory->stockable_type, $inventory->stockable_id)
        );
    }

    private function authorizeStockable(Product|Variant|null $stockable): void
    {
        $user = auth()->user();

        if ($user?->isAdmin()) {
            return;
        }

        abort_if($user === null, 401, 'Authentication required.');

        $organizationId = $stockable !== null ? $this->organizationIdFor($stockable) : null;

        abort_unless(
            $organizationId !== null
                && $user->hasActiveMembershipInOrganization($organizationId),
            403,
            'You do not have access to this inventory record.'
        );
    }

    private function organizationIdFor(Product|Variant $stockable): ?int
    {
        if ($stockable instanceof Product) {
            return $stockable->organization_id !== null ? (int) $stockable->organization_id : null;
        }

        return $stockable->product?->organization_id !== null
            ? (int) $stockable->product->organization_id
            : null;
    }

    private function assertWarehouseBelongsToTenant(int $warehouseId, int $tenantId): void
    {
        $warehouse = Warehouse::query()->find($warehouseId);

        abort_if($warehouse === null, 422, 'The selected warehouse could not be found.');

        abort_unless(
            (int) $warehouse->tenant_id === $tenantId,
            422,
            'The selected warehouse does not belong to the inventory tenant.'
        );
    }
}
