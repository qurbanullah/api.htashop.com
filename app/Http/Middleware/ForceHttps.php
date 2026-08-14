<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ForceHttps
{
    /**
     * Handle the incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        // If X-Forwarded-Proto header is set by proxy, update the request scheme
        if ($request->hasHeader('X-Forwarded-Proto')) {
            $proto = $request->header('X-Forwarded-Proto');
            if (in_array($proto, ['http', 'https'])) {
                // Set the REQUEST_SCHEME server variable
                $request->server->set('REQUEST_SCHEME', $proto);

                // Set HTTPS variable for Laravel's isSecure() check
                if ($proto === 'https') {
                    $request->server->set('HTTPS', 'on');
                } else {
                    $request->server->set('HTTPS', 'off');
                }
            }
        }

        return $next($request);
    }
}
