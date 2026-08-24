<?php

namespace App\Services\Gdpr;

use App\Models\GdprConsent;

class GdprConsentService
{
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
        return GdprConsent::create([
            'consent_token' => $consentToken,
            'user_id' => $userId,
            'categories' => $categories,
            'policy_version' => $policyVersion,
            'source' => $source,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent ? mb_substr($userAgent, 0, 500) : null,
            'accepted_at' => now(),
        ]);
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

        return $query->delete();
    }
}
