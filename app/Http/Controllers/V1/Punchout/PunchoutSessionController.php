<?php

namespace App\Http\Controllers\V1\Punchout;

use App\Actions\Punchout\ExchangeHandoffCodeAction;
use App\Actions\Punchout\TouchPunchoutSessionAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Punchout\PunchoutExchangeRequest;
use App\Http\Resources\V1\Punchout\PunchoutBootstrapResource;
use App\Http\Responses\V1\ApiResponse;
use App\Models\PunchoutSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PunchoutSessionController extends Controller
{
    public function __construct(
        protected ExchangeHandoffCodeAction $exchangeHandoffCodeAction,
        protected TouchPunchoutSessionAction $touchPunchoutSessionAction,
    ) {}

    public function exchange(PunchoutExchangeRequest $request): JsonResponse
    {
        [$session, $accessToken] = $this->exchangeHandoffCodeAction->handle($request->validated('code'));

        return ApiResponse::success(
            new PunchoutBootstrapResource($session, $accessToken),
            'Punchout session resolved.',
        );
    }

    public function show(Request $request): JsonResponse
    {
        return ApiResponse::success(
            new PunchoutBootstrapResource($this->session($request)),
            'Punchout session retrieved.',
        );
    }

    public function heartbeat(Request $request): JsonResponse
    {
        $session = $this->touchPunchoutSessionAction->handle($this->session($request));

        return ApiResponse::success([
            'expires_at' => optional($session->expires_at)?->toIso8601String(),
            'idle_expires_at' => optional($session->idle_expires_at)?->toIso8601String(),
        ], 'Punchout session extended.');
    }

    public function cancel(Request $request): JsonResponse
    {
        $session = $this->session($request);
        $session->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);

        return ApiResponse::success(null, 'Punchout session cancelled.');
    }

    private function session(Request $request): PunchoutSession
    {
        $session = $request->attributes->get('punchout_session');

        abort_unless($session instanceof PunchoutSession, 500, 'Punchout session was not resolved.');

        return $session;
    }
}
