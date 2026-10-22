<?php

namespace App\Actions\Punchout;

use App\Models\PunchoutSession;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ExchangeHandoffCodeAction
{
    public function __construct(
        protected TouchPunchoutSessionAction $touchPunchoutSessionAction,
    ) {}

    /**
     * @return array{0: PunchoutSession, 1: string}
     */
    public function handle(string $code): array
    {
        $session = PunchoutSession::query()
            ->with('tenant')
            ->where('handoff_code_hash', hash('sha256', $code))
            ->first();

        if (! $session || $session->handoff_code_used_at !== null) {
            throw ValidationException::withMessages([
                'code' => ['The punchout handoff code is invalid or has already been used.'],
            ]);
        }

        if ($session->handoff_code_expires_at === null || $session->handoff_code_expires_at->isPast()) {
            throw ValidationException::withMessages([
                'code' => ['The punchout handoff code has expired.'],
            ]);
        }

        if ($session->isCancelled()) {
            throw ValidationException::withMessages([
                'code' => ['The punchout session has been cancelled.'],
            ]);
        }

        if ($session->isCompleted()) {
            throw ValidationException::withMessages([
                'code' => ['The punchout session has already been completed.'],
            ]);
        }

        if ($session->isExpired()) {
            throw ValidationException::withMessages([
                'code' => ['The punchout session has expired.'],
            ]);
        }

        $accessToken = Str::random(64);

        $session->update([
            'access_token_hash' => hash('sha256', $accessToken),
            'handoff_code_used_at' => now(),
            'status' => $session->status === 'pending' ? 'started' : $session->status,
            'started_at' => $session->started_at ?? now(),
        ]);

        $session = $this->touchPunchoutSessionAction->handle($session);

        return [$session, $accessToken];
    }
}
