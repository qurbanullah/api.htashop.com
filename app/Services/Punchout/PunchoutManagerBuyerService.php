<?php

namespace App\Services\Punchout;

use App\Models\PunchoutBuyer;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Tenant-bound facade over PunchoutBuyerService.
 *
 * Every lookup is constrained by the tenant resolved from the caller's
 * membership. A buyer outside it is a 404, never a 403, so ids cannot be probed.
 */
class PunchoutManagerBuyerService
{
    public function __construct(
        protected PunchoutBuyerService $buyers,
    ) {}

    public function read(int $tenantId, array $filters = []): LengthAwarePaginator
    {
        $filters['tenant_id'] = $tenantId;
        unset($filters['tenant_uuid']);

        return $this->buyers->read($filters);
    }

    public function find(int $tenantId, string $uuid): PunchoutBuyer
    {
        return PunchoutBuyer::query()
            ->with(['organization', 'contract'])
            ->where('tenant_id', $tenantId)
            ->where('uuid', $uuid)
            ->firstOrFail();
    }

    public function create(int $tenantId, array $data): PunchoutBuyer
    {
        unset($data['tenant_id']);

        return $this->buyers->create(['tenant_id' => $tenantId] + $data);
    }

    public function update(PunchoutBuyer $buyer, array $data): PunchoutBuyer
    {
        unset($data['tenant_id']);

        return $this->buyers->update($buyer, $data);
    }

    public function delete(PunchoutBuyer $buyer): bool
    {
        return $this->buyers->delete($buyer);
    }

    public function rotateSecret(PunchoutBuyer $buyer, string $sharedSecret, bool $keepPrevious = true): PunchoutBuyer
    {
        return $this->buyers->rotateSecret($buyer, $sharedSecret, $keepPrevious);
    }

    /**
     * The manager form only needs the tenant's own organizations and contracts.
     *
     * @return array{organizations: mixed, contracts: mixed}
     */
    public function options(int $tenantId, array $filters = []): array
    {
        unset($filters['tenant_id'], $filters['tenant_uuid']);

        $options = $this->buyers->options(['tenant_id' => $tenantId] + $filters);

        return [
            'organizations' => $options['organizations'],
            'contracts' => $options['contracts'],
        ];
    }
}
