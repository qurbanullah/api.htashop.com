<?php

namespace App\Http\Middleware;

use App\Actions\Punchout\ResolvePunchoutSessionByTokenAction;
use App\Models\PunchoutSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolvePunchoutSession
{
    /**
     * Routes whose {uuid} is the session UUID. Other punchout routes also carry
     * a {uuid} (a cart line, for example), so the parameter cannot be trusted
     * blindly: looking a session up by a cart-item UUID yields a spurious 404.
     */
    private const SESSION_ROUTES = [
        'punchout.sessions.show',
        'punchout.sessions.heartbeat',
        'punchout.sessions.cancel',
    ];

    public function __construct(
        protected ResolvePunchoutSessionByTokenAction $resolvePunchoutSessionByTokenAction,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->token($request);

        if ($token === null) {
            abort(401, 'A punchout session token is required.');
        }

        $sessionUuid = $request->routeIs(...self::SESSION_ROUTES)
            ? $request->route('uuid')
            : null;

        $session = is_string($sessionUuid) && $sessionUuid !== ''
            ? $this->resolvePunchoutSessionByTokenAction->handle($sessionUuid, $token)
            : $this->resolvePunchoutSessionByTokenAction->handleByToken($token);

        $this->assertTenantMatchesRoute($request, $session);

        $request->attributes->set('punchout_session', $session);
        $request->attributes->set('punchout_tenant', $session->tenant);

        return $next($request);
    }

    private function token(Request $request): ?string
    {
        $authorization = $request->header('Authorization');

        if (is_string($authorization) && preg_match('/^Punchout\s+(.+)$/i', $authorization, $matches) === 1) {
            return trim($matches[1]);
        }

        $token = $request->header('X-Punchout-Token');

        return is_string($token) && trim($token) !== '' ? trim($token) : null;
    }

    private function assertTenantMatchesRoute(Request $request, PunchoutSession $session): void
    {
        $identifier = $request->route('tenantIdentifier');

        if (! is_string($identifier) || $identifier === '') {
            return;
        }

        $tenant = $session->tenant;

        if ($tenant === null || ! in_array($identifier, [$tenant->uuid, $tenant->slug], true)) {
            abort(404, 'The punchout session does not belong to this tenant.');
        }
    }
}
