<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Responses\V1\ApiResponse;
use App\Services\Ai\TokenBudgetService;
use App\Support\Ai\ChatIdentity;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuses a new reply once a visitor (or the whole platform) has spent its
 * daily token budget, so the provider quota cannot be drained.
 *
 * Complements the request throttle: the throttle caps how often a visitor may
 * ask, this caps how much each reply is allowed to cost overall.
 */
class EnsureChatTokenBudget
{
    public function __construct(
        protected TokenBudgetService $budget,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $limitsEnabled = (int) config('ai.limits.visitor_daily_tokens', 40000) > 0
            || (int) config('ai.limits.global_daily_tokens', 2000000) > 0;

        if (! $limitsEnabled) {
            return $next($request);
        }

        if (! $this->budget->hasBudget(ChatIdentity::visitorKey($request))) {
            return ApiResponse::error(
                'You have reached the assistant usage limit for today. '
                .'Please try again tomorrow or contact support.',
                ['reason' => 'budget_exceeded'],
                429
            );
        }

        return $next($request);
    }
}
