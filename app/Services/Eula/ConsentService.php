<?php

namespace App\Services\Eula;

use App\Actions\Eula\CheckConsentAction;
use App\Actions\Eula\RecordConsentAction;
use App\Helpers\CacheHelper;
use App\Models\Consent;
use App\Models\Eula;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ConsentService
{
    public function __construct(
        protected RecordConsentAction $recordAction,
        protected CheckConsentAction $checkAction,
        protected CacheHelper $cacheHelper,
    ) {}

    /**
     * Record user consent for a EULA.
     */
    public function recordConsent(
        int $userId,
        int $eulaId,
        ?string $consentableType = null,
        ?int $consentableId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        array $metadata = []
    ): Consent {
        DB::beginTransaction();

        try {
            $consent = $this->recordAction->execute([
                'user_id' => $userId,
                'eula_id' => $eulaId,
                'consentable_type' => $consentableType,
                'consentable_id' => $consentableId,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'metadata' => $metadata,
            ]);

            // Clear user-specific consent cache
            $this->clearUserConsentCache($userId);

            DB::commit();

            return $consent->load(['user', 'eula', 'consentable']);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Check if user has consented to a specific EULA.
     */
    public function hasConsented(
        int $userId,
        int $eulaId,
        ?string $consentableType = null,
        ?int $consentableId = null
    ): bool {
        return $this->checkAction->execute($userId, $eulaId, $consentableType, $consentableId);
    }

    /**
     * Check if user has consented to the latest active EULA.
     */
    public function hasConsentedToLatest(
        int $userId,
        ?int $softwareId = null,
        ?int $versionId = null
    ): bool {
        return $this->checkAction->hasConsentedToLatest($userId, $softwareId, $versionId);
    }

    /**
     * Get user's consent history.
     */
    public function getUserConsents(int $userId, int $perPage = 15): LengthAwarePaginator
    {
        return Consent::with(['eula', 'consentable'])
            ->forUser($userId)
            ->orderBy('accepted_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Get consents for a specific EULA.
     */
    public function getEulaConsents(int $eulaId, int $perPage = 15): LengthAwarePaginator
    {
        return Consent::with(['user', 'consentable'])
            ->forEula($eulaId)
            ->orderBy('accepted_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Get consent statistics for a EULA.
     */
    public function getEulaConsentStats(Eula $eula): array
    {
        $consents = Consent::where('eula_id', $eula->id);

        return [
            'total_consents' => $consents->count(),
            'unique_users' => $consents->distinct('user_id')->count('user_id'),
            'recent_7_days' => $consents->recent(7)->count(),
            'recent_30_days' => $consents->recent(30)->count(),
        ];
    }

    /**
     * Clear user-specific consent cache.
     */
    protected function clearUserConsentCache(int $userId): void
    {
        $this->cacheHelper->clearPattern("consent:user:{$userId}:*");
    }
}
