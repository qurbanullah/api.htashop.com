<?php

namespace App\Actions\Punchout;

use App\Models\PunchoutSession;
use App\Models\Tenant;
use App\Support\Punchout\Data\PunchoutCartData;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

class ResolvePunchoutCartSessionAction
{
    public function __construct(
        protected ExpirePunchoutSessionAction $expirePunchoutSessionAction,
        protected TouchPunchoutSessionAction $touchPunchoutSessionAction,
    ) {}

    public function handle(Tenant $tenant, PunchoutCartData $cartData): PunchoutSession
    {
        $session = PunchoutSession::query()
            ->where('tenant_id', $tenant->id)
            ->where('uuid', $cartData->sessionUuid)
            ->first();

        if (! $session || ! $this->tokenMatches($session, $cartData->sessionToken)) {
            throw (new ModelNotFoundException)->setModel(PunchoutSession::class, [$cartData->sessionUuid]);
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

        if ($session->status !== 'started') {
            throw ValidationException::withMessages([
                'session' => ['Punchout session is not active for cart return.'],
            ]);
        }

        if ($session->protocol !== $cartData->protocol) {
            throw ValidationException::withMessages([
                'session' => ['Punchout session protocol does not match the cart request.'],
            ]);
        }

        if ($cartData->buyerCookie !== null && $session->buyer_cookie !== null && $session->buyer_cookie !== $cartData->buyerCookie) {
            throw ValidationException::withMessages([
                'session' => ['Punchout buyer cookie does not match the active session.'],
            ]);
        }

        return $this->touchPunchoutSessionAction->handle($session);
    }

    private function tokenMatches(PunchoutSession $session, string $token): bool
    {
        $hash = hash('sha256', $token);

        if ($session->token_hash !== null && hash_equals($session->token_hash, $hash)) {
            return true;
        }

        return $session->token !== null && hash_equals($session->token, $token);
    }
}
