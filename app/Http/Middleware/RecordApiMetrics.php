<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Counts API requests for the Prometheus `/metrics` endpoint.
 *
 * The route template is used instead of the raw path, so `/orders/{uuid}` is
 * one series, not one per order. Unmatched requests collapse into `unmatched`.
 * Any Redis problem is ignored: metrics must never break a request.
 */
final class RecordApiMetrics
{
    public function handle(Request $request, Closure $next): Response
    {
        $start = microtime(true);

        $response = $next($request);

        // The test suite has no Redis; the metric path is covered separately.
        if (app()->environment('testing')) {
            return $response;
        }

        try {
            $route = $request->route()?->uri() ?? 'unmatched';
            $method = $request->method();
            $status = (string) $response->getStatusCode();
            $durationMs = (int) round((microtime(true) - $start) * 1000);

            Redis::hincrby('metrics:http:requests', $route.'|'.$method.'|'.$status, 1);
            Redis::hincrby('metrics:http:durations', $route.'|'.$method, $durationMs);
            Redis::hincrby('metrics:http:counts', $route.'|'.$method, 1);
        } catch (Throwable) {
            // Observability is best-effort.
        }

        return $response;
    }
}
