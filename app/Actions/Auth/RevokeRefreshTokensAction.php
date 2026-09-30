<?php

namespace App\Actions\Auth;

use App\Enums\RefreshTokenRevokedReasonEnum;
use App\Models\RefreshToken;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Ends sessions, at three scopes.
 *
 * `one` is a sign-out on a single device; `family` is a detected replay, which
 * must kill every token descended from the same sign-in; `forUser` is a password
 * change, which ends every session everywhere.
 *
 * Each scope also retires the access tokens those refresh tokens issued.
 */
class RevokeRefreshTokensAction
{
    public function __construct(private RevokeAccessTokensAction $revokeAccessTokensAction) {}

    /** End one rotation step (a device signing out). */
    public function one(RefreshToken $token, RefreshTokenRevokedReasonEnum $reason): void
    {
        if ($token->isRevoked()) {
            return;
        }

        $this->revokeRows(collect([$token]), $reason);
    }

    /**
     * End every token descended from one sign-in.
     */
    public function family(string $familyId, RefreshTokenRevokedReasonEnum $reason): int
    {
        $tokens = RefreshToken::query()
            ->forFamily($familyId)
            ->whereNull('revoked_at')
            ->get();

        $this->revokeRows($tokens, $reason);

        return $tokens->count();
    }

    /**
     * End every session the user has, on every device.
     */
    public function forUser(int $userId, RefreshTokenRevokedReasonEnum $reason): int
    {
        $tokens = RefreshToken::query()
            ->where('user_id', $userId)
            ->whereNull('revoked_at')
            ->get();

        $this->revokeRows($tokens, $reason);

        return $tokens->count();
    }

    /**
     * End the session that an access token was issued for.
     *
     * This is how sign-out reaches its refresh token without the client having to
     * send it: every refresh row records the access token issued alongside it.
     */
    public function forAccessToken(string $accessTokenId, RefreshTokenRevokedReasonEnum $reason): int
    {
        $familyIds = RefreshToken::query()
            ->where('access_token_id', $accessTokenId)
            ->whereNull('revoked_at')
            ->pluck('family_id')
            ->unique()
            ->all();

        $revoked = 0;

        foreach ($familyIds as $familyId) {
            $revoked += $this->family($familyId, $reason);
        }

        return $revoked;
    }

    /**
     * End every session except the one presenting `keepAccessTokenId`.
     *
     * Used on a password change: the device doing it is trusted (the user just
     * proved the old password), while every other device should be cut off.
     */
    public function forUserExcept(
        int $userId,
        ?string $keepAccessTokenId,
        RefreshTokenRevokedReasonEnum $reason,
    ): int {
        $tokens = RefreshToken::query()
            ->where('user_id', $userId)
            ->whereNull('revoked_at')
            ->when(
                $keepAccessTokenId !== null,
                fn ($query) => $query->where(
                    fn ($inner) => $inner
                        ->whereNull('access_token_id')
                        ->orWhere('access_token_id', '!=', $keepAccessTokenId)
                )
            )
            ->get();

        $this->revokeRows($tokens, $reason);

        return $tokens->count();
    }

    /**
     * @param  Collection<int, RefreshToken>  $tokens
     */
    private function revokeRows(Collection $tokens, RefreshTokenRevokedReasonEnum $reason): void
    {
        if ($tokens->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($tokens, $reason): void {
            $this->revokeAccessTokensAction->execute(
                $tokens->pluck('access_token_id')->all()
            );

            RefreshToken::query()
                ->whereIn('id', $tokens->pluck('id')->all())
                ->whereNull('revoked_at')
                ->update([
                    'revoked_at' => now(),
                    'revoked_reason' => $reason->value,
                    'updated_at' => now(),
                ]);

            Log::info('Refresh tokens revoked', [
                'scope_count' => $tokens->count(),
                'reason' => $reason->value,
                'user_ids' => $tokens->pluck('user_id')->unique()->values()->all(),
            ]);

            if (in_array($reason, RefreshTokenRevokedReasonEnum::compromiseIndicators(), true)) {
                Log::warning('Refresh token replay detected — the session family was revoked.', [
                    'family_ids' => $tokens->pluck('family_id')->unique()->values()->all(),
                    'user_ids' => $tokens->pluck('user_id')->unique()->values()->all(),
                ]);
            }
        });
    }
}
