<?php

namespace App\Services\Value;

use App\Actions\Value\ValueCreateAction;
use App\Actions\Value\ValueDeleteAction;
use App\Actions\Value\ValueReadAction;
use App\Actions\Value\ValueSearchByIdAction;
use App\Actions\Value\ValueUpdateAction;
use App\Models\Product;
use App\Models\Value;
use App\Models\Variant;
use App\Support\Punchout\ValuableTypeRegistry;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Pagination\LengthAwarePaginator;

class ValueService
{
    public function create(array $data): Value
    {
        return (new ValueCreateAction())->handle($data);
    }

    public function read(array $filters = []): LengthAwarePaginator
    {
        $user = auth()->user();

        if (! $user?->hasRole(['super-admin', 'admin'])) {
            $membership = $user->memberships()->where('is_active', true)->first();
            $filters['tenant_id'] = $membership?->tenant_id;
            $filters['organization_id'] = $membership?->organization_id;
            $this->assertCanAccessValuable($filters, $user);
        }

        return (new ValueReadAction())->handle($filters);
    }

    public function searchById(int $id): Value
    {
        $value = (new ValueSearchByIdAction())->handle($id);
        $this->assertCanAccessValue($value);

        return $value;
    }

    public function update(Value $value, array $data): Value
    {
        return (new ValueUpdateAction())->handle($value, $data);
    }

    public function delete(Value $value): bool
    {
        return (new ValueDeleteAction())->handle($value);
    }

    protected function assertCanAccessValuable(array $filters, $user): void
    {
        if (! $user instanceof \App\Models\User) {
            throw new AuthorizationException('Unauthenticated values access is not allowed.');
        }

        $valuableType = data_get($filters, 'valuable_type');
        $valuableUuid = data_get($filters, 'valuable_uuid');

        if (! $valuableType || ! $valuableUuid) {
            return;
        }

        $membership = $user->memberships()->where('is_active', true)->first();

        if (! $membership) {
            throw new AuthorizationException('An active membership is required to access values.');
        }

        $valuable = ValuableTypeRegistry::resolveModel($valuableType, $valuableUuid);

        if (! $valuable) {
            return;
        }

        if ($valuable instanceof Product) {
            if ($membership->tenant_id !== $valuable->tenant_id || $membership->organization_id !== $valuable->organization_id) {
                throw new AuthorizationException('You are not allowed to access values for this product.');
            }
        }

        if ($valuable instanceof Variant) {
            $valuable->loadMissing('product');

            if (! $valuable->product instanceof Product) {
                throw new AuthorizationException('The selected variant is missing its product owner.');
            }

            if ($membership->tenant_id !== $valuable->product->tenant_id || $membership->organization_id !== $valuable->product->organization_id) {
                throw new AuthorizationException('You are not allowed to access values for this variant.');
            }
        }
    }

    protected function assertCanAccessValue(Value $value): void
    {
        $user = auth()->user();

        if ($user?->hasRole(['super-admin', 'admin'])) {
            return;
        }

        if (! $user instanceof \App\Models\User) {
            throw new AuthorizationException('Unauthenticated values access is not allowed.');
        }

        $membership = $user->memberships()->where('is_active', true)->first();

        if (! $membership) {
            throw new AuthorizationException('An active membership is required to access values.');
        }

        if ($membership->tenant_id !== $value->tenant_id) {
            throw new AuthorizationException('You are not allowed to access this value.');
        }

        if ($value->valuable_type === Product::class) {
            $valuable = $value->valuable()->withoutGlobalScopes()->first();

            if (! $valuable instanceof Product || $membership->organization_id !== $valuable->organization_id) {
                throw new AuthorizationException('You are not allowed to access this value.');
            }
        }

        if ($value->valuable_type === Variant::class) {
            $valuable = $value->valuable()->withoutGlobalScopes()->with('product')->first();

            if (! $valuable instanceof Variant || ! $valuable->product instanceof Product) {
                throw new AuthorizationException('You are not allowed to access this value.');
            }

            if ($membership->tenant_id !== $valuable->product->tenant_id || $membership->organization_id !== $valuable->product->organization_id) {
                throw new AuthorizationException('You are not allowed to access this value.');
            }
        }
    }
}
