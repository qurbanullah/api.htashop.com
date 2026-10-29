<?php

namespace App\Services\Price;

use App\Actions\Price\PriceCreateAction;
use App\Actions\Price\PriceDeleteAction;
use App\Actions\Price\PriceReadAction;
use App\Actions\Price\PriceSearchByIdAction;
use App\Actions\Price\PriceUpdateAction;
use App\Models\Price;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

class PriceService
{
    public function __construct(
        protected PriceCreateAction $createAction,
        protected PriceReadAction $readAction,
        protected PriceSearchByIdAction $searchByIdAction,
        protected PriceUpdateAction $updateAction,
        protected PriceDeleteAction $deleteAction,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Price
    {
        $this->authorizeScope(
            (int) data_get($data, 'tenant_id'),
            data_get($data, 'organization_id') !== null ? (int) data_get($data, 'organization_id') : null
        );

        return $this->createAction->handle($data);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Price>
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

            unset($filters['organization_id']);

            if ($requestedTenantId === null) {
                unset($filters['tenant_id']);
            }
        }

        return $this->readAction->handle($filters);
    }

    public function searchById(int $id): Price
    {
        return $this->searchByIdAction->handle($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Price $price, array $data): Price
    {
        $user = Auth::user();

        abort_unless(
            $user?->isAdmin() || $user?->hasActiveMembershipInOrganization((int) $price->organization_id),
            403,
            'You do not have access to this price.'
        );

        if (! $user?->isAdmin() && (array_key_exists('organization_id', $data) || array_key_exists('tenant_id', $data))) {
            $targetOrganizationId = array_key_exists('organization_id', $data)
                ? $data['organization_id']
                : $price->organization_id;

            $this->authorizeScope(
                (int) ($data['tenant_id'] ?? $price->tenant_id),
                $targetOrganizationId !== null ? (int) $targetOrganizationId : null
            );
        }

        return $this->updateAction->handle($price, $data);
    }

    public function delete(Price $price): bool
    {
        $user = Auth::user();

        abort_unless(
            $user?->isAdmin() || $user?->hasActiveMembershipInOrganization((int) $price->organization_id),
            403,
            'You do not have access to this price.'
        );

        return $this->deleteAction->handle($price);
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
