<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\Shipment;
use App\Models\User;

/**
 * Shipment access follows the order it belongs to: admins, or a member of the
 * order's tenant/organization.
 */
class ShipmentPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    /** May this user see the fulfilment plan for the order? */
    public function plan(User $user, Order $order): bool
    {
        return $this->accessesOrder($user, $order);
    }

    public function view(User $user, Shipment $shipment): bool
    {
        return $this->accessesOrder($user, $shipment->order);
    }

    public function create(User $user, Order $order): bool
    {
        return $this->accessesOrder($user, $order);
    }

    public function update(User $user, Shipment $shipment): bool
    {
        return $this->accessesOrder($user, $shipment->order);
    }

    public function delete(User $user, Shipment $shipment): bool
    {
        return $this->accessesOrder($user, $shipment->order);
    }

    private function accessesOrder(User $user, ?Order $order): bool
    {
        if ($order === null) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        if ($order->tenant_id !== null && $user->hasActiveMembershipInTenant((int) $order->tenant_id)) {
            return true;
        }

        return $order->organization_id !== null
            && $user->hasActiveMembershipInOrganization((int) $order->organization_id);
    }
}
