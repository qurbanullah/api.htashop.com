<?php

namespace App\Actions\Punchout;

use App\Models\PunchoutSession;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

class ResolvePunchoutSessionByTokenAction
{
    public function __construct(
        protected ExpirePunchoutSessionAction $expirePunchoutSessionAction,
    ) {}

    public function handle(string $sessionUuid, string $token): PunchoutSession
    {
        $session = PunchoutSession::query()
            ->with('tenant')
            ->where('uuid', $sessionUuid)
            ->first();

        if (! $session || ! $this->tokenMatches($session, $token)) {
            throw (new ModelNotFoundException)->setModel(PunchoutSession::class, [$sessionUuid]);
        }

        return $this->assertUsable($session);
    }

    /**
     * Resolve by capability token alone. The catalog and cart routes carry the
     * tenant in the path, not the session UUID, so the token must identify the
     * session on its own.
     */
    public function handleByToken(string $token): PunchoutSession
    {
        $hash = hash('sha256', $token);

        $session = PunchoutSession::query()
            ->with('tenant')
            ->where(function ($query) use ($hash): void {
                $query->where('access_token_hash', $hash)
                    ->orWhere('token_hash', $hash);
            })
            ->first();

        if (! $session) {
            throw (new ModelNotFoundException)->setModel(PunchoutSession::class, []);
        }

        return $this->assertUsable($session);
    }

    private function assertUsable(PunchoutSession $session): PunchoutSession
    {
        if ($session->isCancelled()) {
            throw ValidationException::withMessages([
                'session' => ['Punchout session has been cancelled.'],
            ]);
        }

        if ($session->isCompleted()) {
            throw ValidationException::withMessages([
                'session' => ['Punchout session has already been completed.'],
            ]);
        }

        if ($session->isExpired() || $session->status === 'expired') {
            $this->expirePunchoutSessionAction->handle($session);

            throw ValidationException::withMessages([
                'session' => ['Punchout session has expired.'],
            ]);
        }

        return $session;
    }

    private function tokenMatches(PunchoutSession $session, string $token): bool
    {
        $hash = hash('sha256', $token);

        if ($session->access_token_hash !== null && hash_equals($session->access_token_hash, $hash)) {
            return true;
        }

        if ($session->token_hash !== null && hash_equals($session->token_hash, $hash)) {
            return true;
        }

        // Legacy start-page token: kept for one release so buyer configurations
        // that already hold a token continue to work during the rollout.
        return $session->token !== null && hash_equals($session->token, $token);
    }
}
