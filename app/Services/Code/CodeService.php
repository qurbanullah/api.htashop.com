<?php

namespace App\Services\Code;

use App\Actions\Code\CodeCreateAction;
use App\Actions\Code\CodeDeleteAction;
use App\Actions\Code\CodeReadAction;
use App\Actions\Code\CodeSearchByIdAction;
use App\Actions\Code\CodeUpdateAction;
use App\Models\Code;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

class CodeService
{
    public function __construct(
        protected CodeCreateAction $createAction,
        protected CodeReadAction $readAction,
        protected CodeSearchByIdAction $searchByIdAction,
        protected CodeUpdateAction $updateAction,
        protected CodeDeleteAction $deleteAction,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Code
    {
        $this->authorizeScope(
            (int) data_get($data, 'tenant_id'),
            data_get($data, 'organization_id') !== null ? (int) data_get($data, 'organization_id') : null
        );

        return $this->createAction->handle($data);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Code>
     */
    public function read(array $filters = []): LengthAwarePaginator
    {
        $user = Auth::user();

        if (! $user?->isAdmin()) {
            abort_if($user === null, 401, 'Authentication required.');

            $allowedOrganizationIds = $user->activeOrganizationIds();

            abort_if(
                $allowedOrganizationIds === [],
                403,
                'No active organization is associated with your account.'
            );

            $requestedOrganizationId = data_get($filters, 'organization_id');

            if ($requestedOrganizationId !== null) {
                abort_unless(
                    in_array((int) $requestedOrganizationId, $allowedOrganizationIds, true),
                    403,
                    'You do not have access to this organization.'
                );
            }

            $requestedTenantId = data_get($filters, 'tenant_id');

            if ($requestedTenantId !== null) {
                abort_unless(
                    $user->hasActiveMembershipInTenant((int) $requestedTenantId),
                    403,
                    'You do not have access to this tenant.'
                );
            }

            $filters['organization_ids'] = $requestedOrganizationId !== null
                ? [(int) $requestedOrganizationId]
                : $allowedOrganizationIds;

            $filters['tenant_ids'] = $requestedTenantId !== null
                ? [(int) $requestedTenantId]
                : $user->activeTenantIds();

            // Only fall back to tenant-level codes when the caller did not ask
            // for a specific organization.
            $filters['include_tenant_level'] = $requestedOrganizationId === null;

            unset($filters['organization_id']);

            if ($requestedTenantId === null) {
                unset($filters['tenant_id']);
            }
        }

        return $this->readAction->handle($filters);
    }

    public function searchById(int $id): Code
    {
        return $this->searchByIdAction->handle($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Code $code, array $data): Code
    {
        $this->ensureCanAccess($code);

        $user = Auth::user();

        if (! $user?->isAdmin() && (array_key_exists('organization_id', $data) || array_key_exists('tenant_id', $data))) {
            $targetOrganizationId = array_key_exists('organization_id', $data)
                ? $data['organization_id']
                : $code->organization_id;

            $this->authorizeScope(
                (int) ($data['tenant_id'] ?? $code->tenant_id),
                $targetOrganizationId !== null ? (int) $targetOrganizationId : null
            );
        }

        return $this->updateAction->handle($code, $data);
    }

    public function delete(Code $code): bool
    {
        $this->ensureCanAccess($code);

        return $this->deleteAction->handle($code);
    }

    private function ensureCanAccess(Code $code): void
    {
        $user = Auth::user();

        abort_unless(
            $user?->isAdmin()
                || ($user?->hasActiveMembershipInTenant((int) $code->tenant_id)
                    && ($code->organization_id === null
                        || $user->hasActiveMembershipInOrganization((int) $code->organization_id))),
            403,
            'You do not have access to this code.'
        );
    }

    private function authorizeScope(int $tenantId, ?int $organizationId): void
    {
        $user = Auth::user();

        if ($user?->isAdmin()) {
            return;
        }

        abort_if($user === null, 401, 'Authentication required.');

        abort_unless(
            $user->hasActiveMembershipInTenant($tenantId)
                && ($organizationId === null || $user->hasActiveMembershipInOrganization($organizationId)),
            403,
            'You do not have access to this organization.'
        );
    }
}
