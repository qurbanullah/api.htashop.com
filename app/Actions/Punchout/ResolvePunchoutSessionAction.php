<?php

namespace App\Actions\Punchout;

use App\Models\PunchoutSession;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

class ResolvePunchoutSessionAction
{
    public function __construct(
        protected ExpirePunchoutSessionAction $expirePunchoutSessionAction,
        protected TouchPunchoutSessionAction $touchPunchoutSessionAction,
    ) {}

    public function handle(Tenant $tenant, string $sessionUuid, string $token): PunchoutSession
    {
        $session = PunchoutSession::query()
            ->where('tenant_id', $tenant->id)
            ->where('uuid', $sessionUuid)
            ->first();

        if (! $session || ! $this->tokenMatches($session, $token)) {
            throw (new ModelNotFoundException)->setModel(PunchoutSession::class, [$sessionUuid]);
        }

        if ($session->isCancelled()) {
            throw ValidationException::withMessages([
                'session' => ['Punchout session has been cancelled.'],
            ]);
        }

        if ($session->isExpired() || $session->status === 'expired') {
            $this->expirePunchoutSessionAction->handle($session);

            throw ValidationException::withMessages([
                'session' => ['Punchout session has expired.'],
            ]);
        }

        if ($session->status === 'completed') {
            throw ValidationException::withMessages([
                'session' => ['Punchout session has already been completed.'],
            ]);
        }

        if ($session->status === 'pending') {
            $session->status = 'started';
            $session->started_at = now();
            $session->save();
        }

        return $this->touchPunchoutSessionAction->handle($session);
    }

    private function tokenMatches(PunchoutSession $session, string $token): bool
    {
        $hash = hash('sha256', $token);

        if ($session->token_hash !== null && hash_equals($session->token_hash, $hash)) {
            return true;
        }

        // One-release compatibility with the legacy plaintext token column.
        return $session->token !== null && hash_equals($session->token, $token);
    }
}
