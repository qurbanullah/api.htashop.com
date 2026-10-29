<?php

namespace App\Services\Product;

use App\Actions\Product\ProductCreateAction;
use App\Actions\Product\ProductDeleteAction;
use App\Actions\Product\ProductReadAction;
use App\Actions\Product\ProductSearchByUuidAction;
use App\Actions\Product\ProductUpdateAction;
use App\Helpers\CacheHelper;
use App\Models\Product;
use Illuminate\Pagination\LengthAwarePaginator;

class ProductService
{
    public function __construct(
        protected ProductCreateAction $createAction,
        protected ProductReadAction $readAction,
        protected ProductSearchByUuidAction $searchByUuidAction,
        protected ProductUpdateAction $updateAction,
        protected ProductDeleteAction $deleteAction,
        protected CacheHelper $cacheHelper,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Product
    {
        $user = auth()->user();

        if ($user?->isAdmin()) {
            abort_if(
                empty($data['tenant_id']) || empty($data['organization_id']),
                422,
                'tenant_id and organization_id are required.'
            );

            return $this->createAction->handle($data);
        }

        $tenantId = $data['tenant_id'] ?? null;
        $organizationId = $data['organization_id'] ?? null;

        if (! $tenantId || ! $organizationId) {
            $membership = $user?->primaryMembership();
            abort_if(! $membership, 403, 'No active organization is associated with your account.');

            $data['tenant_id'] = $tenantId ?: $membership->tenant_id;
            $data['organization_id'] = $organizationId ?: $membership->organization_id;
        }

        abort_unless(
            $user?->activeMemberships()
                ->where('tenant_id', (int) $data['tenant_id'])
                ->where('organization_id', (int) $data['organization_id'])
                ->exists(),
            403,
            'You do not have access to this organization.'
        );

        return $this->createAction->handle($data);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Product>
     */
    public function read(array $filters = []): LengthAwarePaginator
    {
        $user = auth()->user();

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

    public function searchByUuid(string $key): Product
    {
        // Full UUIDs are cached. Composite {slug}-{uuid8} route keys resolve directly
        // so cache invalidation by full UUID always stays correct.
        if (preg_match('/^[0-9a-fA-F-]{36}$/', $key) === 1) {
            return CacheHelper::remember([], "products:{$key}", 600, fn () => $this->searchByUuidAction->handle($key));
        }

        return $this->searchByUuidAction->handle($key);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Product $product, array $data): Product
    {
        $user = auth()->user();

        abort_unless(
            $user?->isAdmin() || $user?->hasActiveMembershipInOrganization((int) $product->organization_id),
            403,
            'You do not have access to this product.'
        );

        if (! $user?->isAdmin() && array_key_exists('organization_id', $data)) {
            abort_unless(
                $user?->hasActiveMembershipInOrganization((int) $data['organization_id']),
                403,
                'You do not have access to this organization.'
            );
        }

        $updated = $this->updateAction->handle($product, $data);
        $this->cacheHelper->clearPattern("products:{$product->uuid}");

        return $updated;
    }

    public function delete(Product $product): bool
    {
        $user = auth()->user();

        abort_unless(
            $user?->isAdmin() || $user?->hasActiveMembershipInOrganization((int) $product->organization_id),
            403,
            'You do not have access to this product.'
        );

        $deleted = $this->deleteAction->handle($product);
        $this->cacheHelper->clearPattern("products:{$product->uuid}");

        return $deleted;
    }
}
