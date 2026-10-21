<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gives every request a correlation id.
 *
 * The id is written into the request attributes (so the audit logger can read
 * it), into the log context, and into the response as `X-Request-Id`. A client
 * supplied id is accepted only when it is short and safe; otherwise a fresh
 * ULID is generated.
 */
final class AssignRequestId
{
    public const ATTRIBUTE = 'request_id';

    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $this->resolve($request);

        $request->attributes->set(self::ATTRIBUTE, $requestId);

        Log::withContext([
            'request_id' => $requestId,
            'trace_id' => $this->traceId($request),
        ]);

        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }

    public static function fromRequest(?Request $request = null): ?string
    {
        $request ??= request();

        $value = $request->attributes->get(self::ATTRIBUTE);

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function resolve(Request $request): string
    {
        $candidate = trim((string) $request->header('X-Request-Id'));

        if ($candidate !== '' && preg_match('/^[A-Za-z0-9._:-]{8,64}$/', $candidate) === 1) {
            return $candidate;
        }

        return (string) Str::ulid();
    }

    private function traceId(Request $request): ?string
    {
        $traceparent = trim((string) $request->header('traceparent'));

        if ($traceparent === '') {
            return null;
        }

        // W3C traceparent: version-traceid-spanid-flags.
        $parts = explode('-', $traceparent);

        return isset($parts[1]) && preg_match('/^[a-f0-9]{32}$/i', $parts[1]) === 1
            ? strtolower($parts[1])
            : null;
    }
}
