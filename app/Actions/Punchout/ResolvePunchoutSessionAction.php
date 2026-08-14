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
    ) {
    }

    public function handle(Tenant $tenant, string $sessionUuid, string $token): PunchoutSession
    {
        $session = PunchoutSession::query()
            ->where('tenant_id', $tenant->id)
            ->where('uuid', $sessionUuid)
            ->where('token', $token)
            ->first();

        if (!$session) {
            throw (new ModelNotFoundException())->setModel(PunchoutSession::class, [$sessionUuid]);
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

        if ($session->status === 'pending') {
            $session->status = 'started';
            $session->started_at = now();
        }

        $session->last_activity_at = now();
        $session->save();

        return $session->fresh();
    }
}
