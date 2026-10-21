<?php

namespace App\Services\Gdpr;

use App\Models\GdprConsent;
use App\Models\User;
use App\Services\Audit\AuditLogger;

class GdprConsentService
{
    public function __construct(
        protected AuditLogger $auditLogger,
    ) {}
    /**
     * Record a GDPR consent choice (banner, settings, or account).
     */
    public function record(
        string $consentToken,
        array $categories,
        string $policyVersion,
        string $source,
        ?int $userId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): GdprConsent {
        $consent = GdprConsent::create([
            'consent_token' => $consentToken,
            'user_id' => $userId,
            'categories' => $categories,
            'policy_version' => $policyVersion,
            'source' => $source,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent ? mb_substr($userAgent, 0, 500) : null,
            'accepted_at' => now(),
        ]);

        $this->auditLogger->record(
            $userId ? User::find($userId) : null,
            'gdpr.consent.recorded',
            $consent,
            [
                'new_values' => [
                    'categories' => $categories,
                    'policy_version' => $policyVersion,
                    'source' => $source,
                ],
                'description' => 'GDPR consent recorded',
                'meta' => [
                    'consent_token_hash' => hash('sha256', $consentToken),
                ],
            ],
        );

        return $consent;
    }

    /**
     * Get the most recent consent for a token (and/or user).
     */
    public function latest(string $consentToken, ?int $userId = null): ?GdprConsent
    {
        return GdprConsent::query()
            ->latestFor($consentToken, $userId)
            ->first();
    }

    /**
     * Withdraw consent — removes all recorded choices for the token/user.
     */
    public function withdraw(string $consentToken, ?int $userId = null): int
    {
        $query = GdprConsent::where('consent_token', $consentToken);

        if ($userId) {
            $query->orWhere('user_id', $userId);
        }

        $deleted = $query->delete();

        $this->auditLogger->record(
            $userId ? User::find($userId) : null,
            'gdpr.consent.withdrawn',
            ['type' => GdprConsent::class],
            [
                'description' => 'GDPR consent withdrawn',
                'meta' => [
                    'consent_token_hash' => hash('sha256', $consentToken),
                    'deleted' => $deleted,
                ],
            ],
        );

        return $deleted;
    }
}
