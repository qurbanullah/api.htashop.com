<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Actions\Auth\CreateRefreshTokenAction;
use App\Actions\Auth\IssueAccessTokenAction;
use App\Actions\Auth\PruneRefreshTokensAction;
use App\Actions\Auth\RevokeAccessTokensAction;
use App\Actions\Auth\RevokeRefreshTokensAction;
use App\Actions\Auth\RotateRefreshTokenAction;
use App\Enums\RefreshTokenFailureEnum;
use App\Enums\RefreshTokenRevokedReasonEnum;
use App\Exceptions\RefreshTokenException;
use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Session lifecycle: start, rotate, revoke.
 *
 * A session is a short-lived access token plus a long-lived, single-use refresh
 * token. Rotation is what makes the refresh token worth storing hashed: since it
 * is exchanged for a new one every time, a stolen copy only works until the real
 * client refreshes — and that is exactly the moment theft becomes detectable.
 *
 * Two situations look identical from the outside and must not be confused:
 *
 *  - the client refreshed, the response was lost, and it still holds the previous
 *    token — recoverable, and forcing a re-login here would be a bug the user
 *    feels; or
 *  - two parties genuinely hold the token — a compromise, which must end the
 *    whole rotation family.
 *
 * The successor tells them apart: if it was never presented, only one party can
 * have been refreshing, so the session continues from a fresh branch of the same
 * family. If it *was* presented, someone else already advanced the chain, and the
 * family is revoked.
 */
class RefreshTokenService
{
    public function __construct(
        private CreateRefreshTokenAction $createRefreshTokenAction,
        private RotateRefreshTokenAction $rotateRefreshTokenAction,
        private RevokeRefreshTokensAction $revokeRefreshTokensAction,
        private PruneRefreshTokensAction $pruneRefreshTokensAction,
        private IssueAccessTokenAction $issueAccessTokenAction,
        private RevokeAccessTokensAction $revokeAccessTokensAction,
    ) {}

    /**
     * Start a session after a successful sign-in.
     *
     * @return array{access_token: string, expires_in: int, refresh_token: string, refresh_expires_at: Carbon}
     */
    public function startSession(User $user, Request $request): array
    {
        $access = $this->issueAccessTokenAction->execute($user);
        $refresh = $this->persist($user, $request, $access['id']);

        return [
            'access_token' => $access['token'],
            'expires_in' => $access['expires_in'],
            'refresh_token' => $refresh['token'],
            'refresh_expires_at' => $refresh['expires_at'],
        ];
    }

    /**
     * Exchange a refresh token for the next one in its family.
     *
     * @return array{access_token: string, expires_in: int, refresh_token: string, refresh_expires_at: Carbon, user: User}
     *
     * @throws RefreshTokenException when the token cannot be exchanged
     */
    public function rotate(string $presented, Request $request): array
    {
        $current = RefreshToken::query()
            ->where('token_hash', RefreshToken::hashToken($presented))
            ->first();

        if ($current === null) {
            throw new RefreshTokenException(RefreshTokenFailureEnum::INVALID);
        }

        if ($current->isRevoked()) {
            return $this->handleRevoked($current, $request);
        }

        if ($current->isExpired()) {
            throw new RefreshTokenException(RefreshTokenFailureEnum::EXPIRED);
        }

        if ($current->isIdle()) {
            $this->revokeRefreshTokensAction->one($current, RefreshTokenRevokedReasonEnum::LOGOUT);

            throw new RefreshTokenException(RefreshTokenFailureEnum::IDLE_EXPIRED);
        }

        return $this->advance($current, $request);
    }

    /**
     * End the session an access token belongs to.
     *
     * Matching on the access token (rather than the refresh token) is what lets
     * sign-out work without the refresh cookie being sent to the logout endpoint.
     */
    public function revokeSessionForAccessToken(string $accessTokenId, RefreshTokenRevokedReasonEnum $reason): int
    {
        return $this->revokeRefreshTokensAction->forAccessToken($accessTokenId, $reason);
    }

    /**
     * End every session for a user (deactivation).
     */
    public function revokeAllForUser(User $user, RefreshTokenRevokedReasonEnum $reason): int
    {
        return $this->revokeRefreshTokensAction->forUser($user->id, $reason);
    }

    /**
     * End every session for a user except the one making the request.
     *
     * A password change is the usual caller: the device proving the old password
     * stays signed in, every other device is cut off.
     */
    public function revokeOtherSessionsForUser(
        User $user,
        ?string $keepAccessTokenId,
        RefreshTokenRevokedReasonEnum $reason,
    ): int {
        return $this->revokeRefreshTokensAction->forUserExcept(
            $user->id,
            $keepAccessTokenId,
            $reason
        );
    }

    /**
     * Remove tokens that expired longer ago than the retention window.
     */
    public function prune(): int
    {
        return $this->pruneRefreshTokensAction->execute(
            now()->subSeconds((int) config('auth_tokens.prune_after'))
        );
    }

    /**
     * Recover from a lost response, or treat the replay as a compromise.
     *
     * @return array{access_token: string, expires_in: int, refresh_token: string, refresh_expires_at: Carbon, user: User}
     */
    private function handleRevoked(RefreshToken $current, Request $request): array
    {
        $wasRotated = $current->revoked_reason === RefreshTokenRevokedReasonEnum::ROTATED;
        $successor = $current->successor;

        if ($wasRotated && $current->hasUnusedSuccessor() && $successor !== null) {
            // Nobody ever presented the successor, so the client never received
            // it. Retire the orphan and continue the same session.
            $this->revokeRefreshTokensAction->one($successor, RefreshTokenRevokedReasonEnum::RECOVERED);

            return $this->advance($current, $request);
        }

        if ($wasRotated) {
            // The successor was already used: two parties hold tokens from this
            // lineage, so end all of it rather than guess which one is the owner.
            $this->revokeRefreshTokensAction->family(
                $current->family_id,
                RefreshTokenRevokedReasonEnum::REUSE_DETECTED
            );

            throw new RefreshTokenException(RefreshTokenFailureEnum::REUSED);
        }

        throw new RefreshTokenException(RefreshTokenFailureEnum::REVOKED);
    }

    /**
     * @return array{access_token: string, expires_in: int, refresh_token: string, refresh_expires_at: Carbon, user: User}
     */
    private function advance(RefreshToken $current, Request $request): array
    {
        /** @var User $user */
        $user = $current->user ?? User::query()->findOrFail($current->user_id);

        // The access token is created first so its id can be bound to the refresh
        // token that replaces it, keeping the pair revocable together.
        $access = $this->issueAccessTokenAction->execute($user);
        $token = RefreshToken::generateToken();

        $this->rotateRefreshTokenAction->execute($current, [
            'token_hash' => RefreshToken::hashToken($token),
            'access_token_id' => $access['id'],
            // The chain keeps its original deadline: refreshing must not extend
            // a session indefinitely.
            'expires_at' => $current->expires_at,
            'ip_address' => $request->ip(),
            'user_agent' => $this->userAgent($request),
        ]);

        // Retire the access token this rotation replaced, so refreshing shrinks
        // the set of usable credentials rather than growing it. A client that
        // lost this response is not stranded: its (now dead) access token gets a
        // 401, which sends it back here to recover.
        if ($current->access_token_id !== null) {
            $this->revokeAccessTokensAction->execute([$current->access_token_id]);
        }

        return [
            'access_token' => $access['token'],
            'expires_in' => $access['expires_in'],
            'refresh_token' => $token,
            'refresh_expires_at' => $current->expires_at,
            'user' => $user,
        ];
    }

    /**
     * @return array{token: string, expires_at: Carbon}
     */
    private function persist(User $user, Request $request, ?string $accessTokenId): array
    {
        $token = RefreshToken::generateToken();
        $expiresAt = now()->addSeconds((int) config('auth_tokens.refresh_ttl'));

        $this->createRefreshTokenAction->execute([
            'user_id' => $user->id,
            'token_hash' => RefreshToken::hashToken($token),
            // A new sign-in starts a new lineage.
            'family_id' => (string) Str::uuid(),
            'device_id' => $this->deviceId($request),
            'access_token_id' => $accessTokenId,
            'expires_at' => $expiresAt,
            'ip_address' => $request->ip(),
            'user_agent' => $this->userAgent($request),
        ]);

        return ['token' => $token, 'expires_at' => $expiresAt];
    }

    /**
     * The install id the native app sends, used to group a device's sessions.
     */
    private function deviceId(Request $request): ?string
    {
        $deviceId = trim((string) $request->header('X-Device-Id', ''));

        return $deviceId === '' ? null : mb_substr($deviceId, 0, 191);
    }

    private function userAgent(Request $request): ?string
    {
        $userAgent = trim((string) $request->userAgent());

        return $userAgent === '' ? null : mb_substr($userAgent, 0, 255);
    }
}
