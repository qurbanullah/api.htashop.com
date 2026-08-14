<?php

namespace App\Services\Eula;

use App\Actions\Eula\ActivateEulaAction;
use App\Actions\Eula\CreateEulaAction;
use App\Actions\Eula\DeleteEulaAction;
use App\Actions\Eula\GetActiveEulaAction;
use App\Actions\Eula\UpdateEulaAction;
use App\Helpers\CacheHelper;
use App\Models\Eula;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class EulaService
{
    public function __construct(
        protected CreateEulaAction $createAction,
        protected UpdateEulaAction $updateAction,
        protected DeleteEulaAction $deleteAction,
        protected ActivateEulaAction $activateAction,
        protected GetActiveEulaAction $getActiveAction,
        protected CacheHelper $cacheHelper,
    ) {}

    /**
     * Get paginated list of EULAs with filters.
     */
    public function list(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Eula::query()
            ->with(['creator:id,name,email', 'updater:id,name,email', 'software', 'version'])
            ->withCount('consents');

        // Apply filters
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['software_id'])) {
            $query->where('software_id', $filters['software_id']);
        }

        if (isset($filters['version_id'])) {
            $query->where('version_id', $filters['version_id']);
        }

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('version', 'like', "%{$search}%")
                  ->orWhereHas('software', function ($sq) use ($search) {
                      $sq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        // Sorting
        $sortBy = $filters['sort'] ?? 'created_at';
        $sortOrder = $filters['order'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        return $query->paginate($perPage);
    }

    /**
     * Get a single EULA by UUID.
     */
    public function getByUuid(string $uuid): ?Eula
    {
        return Eula::with(['creator', 'updater', 'software', 'version'])
            ->withCount('consents')
            ->where('uuid', $uuid)
            ->first();
    }

    /**
     * Get the active EULA for download/consent purposes.
     */
    public function getActiveEula(?int $softwareId = null, ?int $versionId = null): ?Eula
    {
        return $this->getActiveAction->execute($softwareId, $versionId);
    }

    /**
     * Create a new EULA.
     */
    public function create(array $data, ?int $createdBy = null): Eula
    {
        DB::beginTransaction();

        try {
            $data['created_by'] = $createdBy;
            $eula = $this->createAction->execute($data);

            // Clear cache
            $this->clearCache();

            DB::commit();

            return $eula->load(['creator', 'updater', 'software', 'version']);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Update an existing EULA.
     */
    public function update(Eula $eula, array $data, ?int $updatedBy = null): Eula
    {
        DB::beginTransaction();

        try {
            $data['updated_by'] = $updatedBy;
            $updatedEula = $this->updateAction->execute($eula, $data);

            // Clear cache
            $this->clearCache();

            DB::commit();

            return $updatedEula->load(['creator', 'updater', 'software', 'version']);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Delete a EULA.
     */
    public function delete(Eula $eula): bool
    {
        DB::beginTransaction();

        try {
            $result = $this->deleteAction->execute($eula);

            // Clear cache
            $this->clearCache();

            DB::commit();

            return $result;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Activate a EULA (and optionally deactivate others).
     */
    public function activate(Eula $eula, bool $deactivateOthers = true): Eula
    {
        DB::beginTransaction();

        try {
            $activatedEula = $this->activateAction->execute($eula, $deactivateOthers);

            // Clear cache
            $this->clearCache();

            DB::commit();

            return $activatedEula->load(['creator', 'updater', 'software', 'version']);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Get EULA statistics.
     */
    public function getStatistics(): array
    {
        return [
            'total' => Eula::count(),
            'active' => Eula::active()->count(),
            'draft' => Eula::where('status', 'draft')->count(),
            'inactive' => Eula::where('status', 'inactive')->count(),
            'total_consents' => \App\Models\Consent::count(),
        ];
    }

    /**
     * Clear all EULA-related caches.
     */
    protected function clearCache(): void
    {
        $this->cacheHelper->clearGroup('eulas');
        $this->cacheHelper->clearPattern('eula:*');
        $this->cacheHelper->clearPattern('consent:*');
    }
}
