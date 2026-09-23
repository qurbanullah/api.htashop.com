<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Responses\V1\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Takes the assistant offline without a deploy (CHAT_ENABLED=false).
 */
class EnsureChatEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('ai.enabled', true)) {
            return ApiResponse::error(
                'The support assistant is currently unavailable. Please contact support.',
                ['reason' => 'chat_disabled'],
                503
            );
        }

        return $next($request);
    }
}
