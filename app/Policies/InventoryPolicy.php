<?php

namespace App\Policies;

use App\Models\Inventory;
use App\Models\Product;
use App\Models\User;
use App\Models\Variant;

class InventoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->activeMemberships()->exists();
    }

    public function view(User $user, Inventory $inventory): bool
    {
        return $this->ownsStockable($user, $inventory);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Inventory $inventory): bool
    {
        return $this->ownsStockable($user, $inventory);
    }

    public function delete(User $user, Inventory $inventory): bool
    {
        return $this->ownsStockable($user, $inventory);
    }

    private function ownsStockable(User $user, Inventory $inventory): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        $organizationId = $this->organizationId($inventory);

        return $organizationId !== null
            && $user->hasActiveMembershipInOrganization($organizationId);
    }

    /**
     * Resolve the owning organization from the stockable. Both the component
     * aliases and the fully-qualified model names are accepted so inventory
     * rows written before the API normalized the morph type stay accessible.
     */
    private function organizationId(Inventory $inventory): ?int
    {
        $type = $inventory->stockable_type;

        if ($type === Product::class || $type === 'product') {
            $organizationId = Product::query()
                ->whereKey($inventory->stockable_id)
                ->value('organization_id');

            return $organizationId !== null ? (int) $organizationId : null;
        }

        if ($type === Variant::class || $type === 'variant') {
            $variant = Variant::query()
                ->with('product')
                ->whereKey($inventory->stockable_id)
                ->first();

            return $variant?->product?->organization_id !== null
                ? (int) $variant->product->organization_id
                : null;
        }

        return null;
    }
}
