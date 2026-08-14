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
    ) {
    }

    public function handle(Tenant $tenant, PunchoutCartData $cartData): PunchoutSession
    {
        $session = PunchoutSession::query()
            ->where('tenant_id', $tenant->id)
            ->where('uuid', $cartData->sessionUuid)
            ->where('token', $cartData->sessionToken)
            ->first();

        if (!$session) {
            throw (new ModelNotFoundException())->setModel(PunchoutSession::class, [$cartData->sessionUuid]);
        }

        if ($session->expires_at && $session->expires_at->isPast()) {
            $this->expirePunchoutSessionAction->handle($session);

            throw ValidationException::withMessages([
                'session' => ['Punchout session has expired.'],
            ]);
        }

        if ($session->status === 'expired') {
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

        $session->last_activity_at = now();
        $session->save();

        return $session->fresh();
    }
}
