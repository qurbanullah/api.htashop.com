<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use App\Models\Tenant;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolve the tenant a manager is acting for from their active membership.
 *
 * The tenant is never taken from the request: a manager must not be able to
 * list or edit another merchant's punchout buyers by sending a different
 * tenant id.
 */
class ResolvePunchoutManagerTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('api') ?? $request->user();
        $tenantId = $user instanceof User ? $this->tenantIdFor($user) : null;

        if ($tenantId === null) {
            return response()->json([
                'success' => false,
                'message' => 'No active tenant is associated with your account.',
            ], 403);
        }

        $request->attributes->set('punchout_manager_tenant_id', $tenantId);

        return $next($request);
    }

    private function tenantIdFor(User $user): ?int
    {
        $memberships = $user->memberships()
            ->where('is_active', true)
            ->orderByDesc('is_primary')
            ->get();

        foreach ($memberships as $membership) {
            $tenantId = $membership->tenant_id !== null
                ? (int) $membership->tenant_id
                : $this->tenantIdFromOrganization($membership->organization_id);

            if ($tenantId === null) {
                continue;
            }

            if (Tenant::query()->whereKey($tenantId)->where('is_active', true)->exists()) {
                return $tenantId;
            }
        }

        return null;
    }

    private function tenantIdFromOrganization(?int $organizationId): ?int
    {
        // A legacy membership may only carry an organization; derive the tenant
        // from it so those managers are not locked out.
        if ($organizationId === null) {
            return null;
        }

        $tenantId = Organization::query()
            ->whereKey($organizationId)
            ->value('tenant_id');

        return $tenantId !== null ? (int) $tenantId : null;
    }
}
