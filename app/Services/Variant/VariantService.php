<?php

namespace App\Services\Variant;

use App\Actions\Variant\VariantUpdateAction;
use App\Models\Product;
use App\Models\Variant;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class VariantService
{
    public function __construct(
        protected VariantUpdateAction $updateAction,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Variant>
     */
    public function read(array $filters = []): LengthAwarePaginator
    {
        $user = auth()->user();

        $query = Variant::query()
            ->when(data_get($filters, 'product_id'), fn ($q, $id) => $q->where('product_id', $id));

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

                $query->where('tenant_id', (int) $requestedTenantId);
            }

            $organizationIds = $requestedOrganizationId !== null
                ? [(int) $requestedOrganizationId]
                : $allowedOrganizationIds;

            $query->whereHas(
                'product',
                fn ($productQuery) => $productQuery->whereIn('organization_id', $organizationIds)
            );
        }

        return $query
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->paginate((int) data_get($filters, 'per_page', 100));
    }

    public function searchByUuid(string $uuid): Variant
    {
        return Variant::where('uuid', $uuid)->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Variant
    {
        $product = Product::query()->findOrFail(data_get($data, 'product_id'));

        $this->authorizeProductAccess($product);

        // Auto-generate slug from name
        if (empty($data['slug']) && ! empty($data['name'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        // Set default if this is the first variant
        if (! empty($data['product_id']) && empty($data['is_default'])) {
            $exists = Variant::where('product_id', $data['product_id'])->exists();
            if (! $exists) {
                $data['is_default'] = true;
            }
        }

        // Unset others if this one is default
        if (! empty($data['is_default']) && ! empty($data['product_id'])) {
            Variant::where('product_id', $data['product_id'])
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }

        return Variant::create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Variant $variant, array $data): Variant
    {
        $this->authorizeProductAccess($variant->product);

        if (array_key_exists('product_id', $data)
            && (int) $data['product_id'] !== (int) $variant->product_id) {
            $targetProduct = Product::query()->findOrFail($data['product_id']);

            $this->authorizeProductAccess($targetProduct);
        }

        // Unset others if this one is becoming default
        if (! empty($data['is_default']) && $variant->product_id) {
            Variant::where('product_id', $variant->product_id)
                ->where('is_default', true)
                ->where('id', '!=', $variant->id)
                ->update(['is_default' => false]);
        }

        return $this->updateAction->handle($variant, $data);
    }

    public function delete(Variant $variant): bool
    {
        $this->authorizeProductAccess($variant->product);

        return $variant->delete() ?? false;
    }

    private function authorizeProductAccess(?Product $product): void
    {
        $user = auth()->user();

        abort_unless(
            $user?->isAdmin()
                || ($product !== null && $user?->hasActiveMembershipInOrganization((int) $product->organization_id)),
            403,
            'You do not have access to this variant.'
        );
    }
}
