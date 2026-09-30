<?php

namespace App\Http\Middleware;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ApiAuthenticate extends Authenticate
{
    /**
     * Handle an incoming request.
     */
    public function handle($request, \Closure $next, ...$guards)
    {
        // Use debug-level logging to avoid noisy production logs and avoid
        // printing token fragments. We only log whether an Authorization header
        // exists so operators can triage header propagation problems.
        // Log::debug('ApiAuthenticate middleware called', [
        //     'path' => $request->path(),
        //     // default to api guard so Passport token driver is used for API routes
        //     'guards' => $guards ?: ['api'],
        //     'has_authorization_header' => $request->hasHeader('Authorization'),
        // ]);

        // If no guards were provided, assume the API guard so tokens are checked
        // with the Passport driver instead of falling back to the default 'web' guard.
        // If no guards were passed, default to the API guard (Passport) so Bearer tokens
        // are validated correctly. Previously an empty guards array meant the framework
        // did not check the api guard and valid tokens were rejected.
        if (empty($guards)) {
            $guards = ['api'];
        }

        // Support httpOnly-cookie auth (storefront) in addition to Bearer tokens
        // (admin/manage/native). If the access-token cookie is present and no
        // Authorization header was sent, promote the cookie to a Bearer header
        // so Passport validates it.
        $accessCookie = (string) config('auth_tokens.cookie.access');

        if (! $request->hasHeader('Authorization') && $request->hasCookie($accessCookie)) {
            $request->headers->set('Authorization', 'Bearer '.$request->cookie($accessCookie));
        }

        try {
            return parent::handle($request, $next, ...$guards);
        } catch (AuthenticationException $e) {
            if ($request->is('api/*')) {
                Log::info('ApiAuthenticate returning JSON 401 for API route', [
                    'path' => $request->path(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated.',
                    'error' => 'Authentication required',
                    // Machine-readable (RFC 6750 wording): the client uses this to
                    // decide whether attempting a token refresh is worthwhile,
                    // instead of matching on the message text.
                    'code' => $request->hasHeader('Authorization') ? 'invalid_token' : 'unauthenticated',
                ], 401);
            }

            throw $e; // Re-throw for non-API routes
        }
    }

    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        if ($request->is('api/*')) {
            return null; // Don't redirect for API routes
        }

        return parent::redirectTo($request);
    }
}
