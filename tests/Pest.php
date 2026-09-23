<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(Tests\TestCase::class, RefreshDatabase::class)->in('Feature');
uses(Tests\TestCase::class, RefreshDatabase::class)->in('Unit');

/**
 * Fake the configured chat provider with a deterministic streamed reply, so
 * tests never reach DeepSeek.
 */
function fakeChatStream(string $text = 'Hello there'): void
{
    config([
        'ai.default' => 'deepseek',
        'ai.providers.deepseek.api_key' => 'test-key',
        'ai.providers.deepseek.base_url' => 'https://api.deepseek.com',
        'ai.providers.deepseek.model' => 'deepseek-chat',
    ]);

    $frame = fn (array $delta, ?string $finish = null) => 'data: ' . json_encode([
        'choices' => [['delta' => $delta, 'finish_reason' => $finish]],
    ]);

    $body = implode("\n\n", [
        $frame(['content' => $text]),
        $frame([], 'stop'),
        'data: ' . json_encode(['choices' => [], 'usage' => ['prompt_tokens' => 12, 'completion_tokens' => 3]]),
        'data: [DONE]',
    ]) . "\n\n";

    Http::fake(['*' => Http::response($body, 200)]);
}

/**
 * The visitor token issued by a chat response, for reuse in a follow-up request
 * (Laravel's test client does not carry cookies between calls).
 */
function chatVisitorToken(\Illuminate\Testing\TestResponse $response): string
{
    $cookie = collect($response->headers->getCookies())
        ->first(fn ($cookie) => $cookie->getName() === \App\Support\Ai\ChatIdentity::COOKIE);

    return (string) ($cookie?->getValue() ?? '');
}
