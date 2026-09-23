<?php

declare(strict_types=1);

namespace App\Services\Ai;

use Illuminate\Support\Facades\Cache;

/**
 * Day-scoped token budget for the support assistant.
 *
 * Request throttling alone is not enough: a visitor can stay within a request
 * rate and still burn a large amount of provider tokens. This adds a per-visitor
 * and a global daily token ceiling, so a bug or an abusive client cannot drain
 * the DeepSeek quota. Counters expire at end of day.
 */
class TokenBudgetService
{
    /**
     * Whether the visitor may start another reply at all.
     */
    public function hasBudget(string $visitorKey): bool
    {
        return $this->visitorRemaining($visitorKey) > 0 && $this->globalRemaining() > 0;
    }

    public function visitorRemaining(string $visitorKey): int
    {
        $limit = (int) config('ai.limits.visitor_daily_tokens', 40000);

        if ($limit <= 0) {
            return PHP_INT_MAX;
        }

        return max(0, $limit - $this->used($this->visitorKeyName($visitorKey)));
    }

    public function globalRemaining(): int
    {
        $limit = (int) config('ai.limits.global_daily_tokens', 2000000);

        if ($limit <= 0) {
            return PHP_INT_MAX;
        }

        return max(0, $limit - $this->used($this->globalKeyName()));
    }

    /**
     * Record tokens consumed by a completed reply.
     */
    public function record(string $visitorKey, int $tokens): void
    {
        $tokens = max(0, $tokens);

        if ($tokens === 0) {
            return;
        }

        $ttl = $this->ttlSeconds();
        $this->increment($this->visitorKeyName($visitorKey), $tokens, $ttl);
        $this->increment($this->globalKeyName(), $tokens, $ttl);
    }

    protected function used(string $key): int
    {
        return (int) Cache::get($key, 0);
    }

    protected function increment(string $key, int $amount, int $ttl): void
    {
        if (! Cache::has($key)) {
            Cache::put($key, 0, $ttl);
        }

        Cache::increment($key, $amount);
    }

    protected function ttlSeconds(): int
    {
        return max(1, now()->endOfDay()->getTimestamp() - now()->getTimestamp());
    }

    protected function visitorKeyName(string $visitorKey): string
    {
        return 'chat:budget:visitor:'.$visitorKey.':'.now()->toDateString();
    }

    protected function globalKeyName(): string
    {
        return 'chat:budget:global:'.now()->toDateString();
    }
}
