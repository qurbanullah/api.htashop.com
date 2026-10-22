<?php

namespace App\Services\Punchout;

use App\Models\Contract;
use App\Models\Organization;
use App\Models\PunchoutBuyer;
use App\Models\PunchoutInboundOrder;
use App\Models\Tenant;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class PunchoutBuyerService
{
    private const ATTRIBUTES = [
        'tenant_id',
        'organization_id',
        'contract_id',
        'name',
        'code',
        'protocol',
        'sender_identity',
        'from_identity',
        'to_identity',
        'oci_username',
        'allowed_return_domains',
        'default_currency',
        'default_locale',
        'settings',
        'is_active',
    ];

    public function read(array $filters = []): LengthAwarePaginator
    {
        $tenantUuid = data_get($filters, 'tenant_uuid');

        return PunchoutBuyer::query()
            ->with(['organization', 'contract'])
            ->when(data_get($filters, 'tenant_id'), fn ($query, $tenantId) => $query->where('tenant_id', $tenantId))
            ->when($tenantUuid, fn ($query) => $query->whereHas(
                'tenant',
                fn ($tenantQuery) => $tenantQuery->where('uuid', $tenantUuid),
            ))
            ->when(data_get($filters, 'protocol'), fn ($query, $protocol) => $query->where('protocol', $protocol))
            ->when(! is_null(data_get($filters, 'is_active')), fn ($query) => $query->where('is_active', (bool) data_get($filters, 'is_active')))
            ->when(data_get($filters, 'search'), function ($query, $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', '%'.$search.'%')
                        ->orWhere('code', 'like', '%'.$search.'%')
                        ->orWhere('sender_identity', 'like', '%'.$search.'%')
                        ->orWhere('from_identity', 'like', '%'.$search.'%')
                        ->orWhere('oci_username', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('name')
            ->paginate((int) data_get($filters, 'per_page', 15));
    }

    public function searchByUuid(string $uuid): PunchoutBuyer
    {
        return PunchoutBuyer::query()
            ->with(['organization', 'contract'])
            ->where('uuid', $uuid)
            ->firstOrFail();
    }

    /**
     * Selectable values for the buyer form. Organizations and contracts are
     * only returned once a tenant is known; loading them tenant-wide would
     * scale with the whole platform rather than the store being configured.
     *
     * @return array{tenants: mixed, organizations: mixed, contracts: mixed}
     */
    public function options(array $filters = []): array
    {
        $tenants = Tenant::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->limit(1000)
            ->get(['id', 'uuid', 'name', 'slug']);

        $tenant = null;

        if (data_get($filters, 'tenant_id')) {
            $tenant = Tenant::query()->find(data_get($filters, 'tenant_id'));
        } elseif (data_get($filters, 'tenant_uuid')) {
            $tenant = Tenant::query()->where('uuid', data_get($filters, 'tenant_uuid'))->first();
        }

        if (! $tenant) {
            return [
                'tenants' => $tenants,
                'organizations' => [],
                'contracts' => [],
            ];
        }

        $search = trim((string) data_get($filters, 'search'));
        $organizationId = data_get($filters, 'organization_id');

        $organizations = Organization::query()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->when($search !== '', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
            ->orderBy('name')
            ->limit(500)
            ->get(['id', 'uuid', 'name', 'slug', 'type']);

        $contracts = Contract::query()
            ->with('buyer:id,name')
            ->where('tenant_id', $tenant->id)
            ->when($organizationId, fn ($query, $organizationId) => $query->where('buyer_id', $organizationId))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', '%'.$search.'%')
                        ->orWhere('code', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('name')
            ->limit(500)
            ->get(['id', 'uuid', 'name', 'code', 'status', 'buyer_id', 'vendor_id', 'catalog_id', 'currency_id'])
            ->map(fn (Contract $contract) => [
                'id' => $contract->id,
                'uuid' => $contract->uuid,
                'name' => $contract->name,
                'code' => $contract->code,
                'status' => $contract->status,
                'buyer_id' => $contract->buyer_id,
                'buyer_name' => $contract->buyer?->name,
                'vendor_id' => $contract->vendor_id,
                'catalog_id' => $contract->catalog_id,
                'currency_id' => $contract->currency_id,
            ]);

        return [
            'tenants' => $tenants,
            'organizations' => $organizations,
            'contracts' => $contracts,
        ];
    }

    public function create(array $data): PunchoutBuyer
    {
        $attributes = $this->attributes($data);

        if (! empty($data['shared_secret'])) {
            $attributes['shared_secret_hash'] = PunchoutBuyer::hashSecret($data['shared_secret']);
            $attributes['secret_rotated_at'] = now();
        }

        if (! empty($data['oci_password'])) {
            $attributes['oci_password_hash'] = PunchoutBuyer::hashSecret($data['oci_password']);
        }

        return PunchoutBuyer::query()->create($attributes)->load(['organization', 'contract']);
    }

    public function update(PunchoutBuyer $buyer, array $data): PunchoutBuyer
    {
        $attributes = $this->attributes($data);

        if (! empty($data['shared_secret'])) {
            // Keep the outgoing secret valid until the next rotation so the
            // buyer's procurement system can roll over without downtime.
            $attributes['previous_shared_secret_hash'] = $buyer->shared_secret_hash;
            $attributes['shared_secret_hash'] = PunchoutBuyer::hashSecret($data['shared_secret']);
            $attributes['secret_rotated_at'] = now();
        }

        if (! empty($data['oci_password'])) {
            $attributes['oci_password_hash'] = PunchoutBuyer::hashSecret($data['oci_password']);
        }

        $buyer->update($attributes);

        return $buyer->fresh(['organization', 'contract']);
    }

    public function rotateSecret(PunchoutBuyer $buyer, string $sharedSecret, bool $keepPrevious = true): PunchoutBuyer
    {
        $buyer->update([
            'previous_shared_secret_hash' => $keepPrevious ? $buyer->shared_secret_hash : null,
            'shared_secret_hash' => PunchoutBuyer::hashSecret($sharedSecret),
            'secret_rotated_at' => now(),
        ]);

        return $buyer->fresh(['organization', 'contract']);
    }

    public function delete(PunchoutBuyer $buyer): bool
    {
        // Buyers with session or order history are deactivated instead of
        // deleted so audit trails keep a resolvable buyer reference.
        $hasHistory = $buyer->sessions()->exists()
            || PunchoutInboundOrder::query()->where('buyer_id', $buyer->id)->exists();

        if ($hasHistory) {
            $buyer->update(['is_active' => false]);

            return true;
        }

        return (bool) $buyer->delete();
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        $attributes = [];

        foreach (self::ATTRIBUTES as $key) {
            if (array_key_exists($key, $data)) {
                $attributes[$key] = $data[$key];
            }
        }

        if (array_key_exists('code', $attributes) && $attributes['code'] !== null) {
            $attributes['code'] = Str::lower(trim((string) $attributes['code']));
        }

        if (array_key_exists('default_currency', $attributes) && $attributes['default_currency'] !== null) {
            $attributes['default_currency'] = Str::upper(trim((string) $attributes['default_currency']));
        }

        if (array_key_exists('default_locale', $attributes) && $attributes['default_locale'] !== null) {
            $attributes['default_locale'] = Str::lower(trim((string) $attributes['default_locale']));
        }

        foreach (['sender_identity', 'from_identity', 'to_identity', 'oci_username', 'name'] as $key) {
            if (array_key_exists($key, $attributes) && is_string($attributes[$key])) {
                $attributes[$key] = trim($attributes[$key]);
            }
        }

        if (array_key_exists('allowed_return_domains', $attributes)) {
            $domains = $attributes['allowed_return_domains'];
            $attributes['allowed_return_domains'] = is_array($domains)
                ? array_values(array_filter(array_map(
                    fn ($domain) => Str::lower(ltrim(trim((string) $domain), '.')),
                    $domains,
                )))
                : null;
        }

        return $attributes;
    }
}
