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
    ) {
    }

    public function create(array $data): Product
    {
        // Auto-resolve tenant/organization from user's active membership
        if (empty($data['tenant_id']) || empty($data['organization_id'])) {
            $user = auth()->user();
            $membership = $user?->memberships()->where('is_active', true)->first();
            if ($membership) {
                $data['tenant_id'] = $data['tenant_id'] ?? $membership->tenant_id;
                $data['organization_id'] = $data['organization_id'] ?? $membership->organization_id;
            }
        }

        return $this->createAction->handle($data);
    }

    public function read(array $filters = []): LengthAwarePaginator
    {
        $user = auth()->user();

        // Non-admin users are always scoped to their active membership tenant and organization.
        if (! $user?->hasRole(['super-admin', 'admin'])) {
            $membership = $user?->memberships()->where('is_active', true)->first();
            $filters['tenant_id'] = $membership?->tenant_id;
            $filters['organization_id'] = $membership?->organization_id;
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

    public function update(Product $product, array $data): Product
    {
        $updated = $this->updateAction->handle($product, $data);
        $this->cacheHelper->clearPattern("products:{$product->uuid}");

        return $updated;
    }

    public function delete(Product $product): bool
    {
        $deleted = $this->deleteAction->handle($product);
        $this->cacheHelper->clearPattern("products:{$product->uuid}");

        return $deleted;
    }
}
