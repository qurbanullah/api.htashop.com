<?php

namespace App\Services\SellerDashboard;

use App\Actions\SellerDashboard\SellerDashboardReadAction;

class SellerDashboardService
{
    public function __construct(
        protected SellerDashboardReadAction $readAction,
    ) {
    }

    public function summary(): array
    {
        $user = auth()->user();
        $tenantId = null;

        // Non-admin users are scoped to their active membership tenant.
        if ($user && ! $user->hasRole(['super-admin', 'admin'])) {
            $membership = $user->memberships()->where('is_active', true)->first();
            $tenantId = $membership?->tenant_id;
        }

        return $this->readAction->handle($tenantId);
    }
}
