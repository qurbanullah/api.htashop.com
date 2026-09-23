<?php

use App\Support\Ai\ChatIdentity;

it('throttles the assistant per visitor', function () {
    fakeChatStream();

    config(['ai.limits.per_minute' => 1, 'ai.limits.per_day' => 100]);

    $token = str_repeat('a', 64);

    $this->withCredentials()
        ->withUnencryptedCookie(ChatIdentity::COOKIE, $token)
        ->postJson('/api/v1/chat/stream', ['message' => 'first'])
        ->assertOk()
        ->streamedContent();

    $this->withCredentials()
        ->withUnencryptedCookie(ChatIdentity::COOKIE, $token)
        ->postJson('/api/v1/chat/stream', ['message' => 'second'])
        ->assertStatus(429);
});

it('refuses a reply once the visitor token budget is exhausted', function () {
    fakeChatStream();

    config([
        'ai.limits.per_minute' => 100,
        'ai.limits.visitor_daily_tokens' => 1,
        'ai.limits.global_daily_tokens' => 1000,
    ]);

    $first = $this->postJson('/api/v1/chat/stream', ['message' => 'hello']);
    $first->assertOk()->streamedContent();

    // The first reply already spent more than the 1-token budget.
    $this->withCredentials()
        ->withUnencryptedCookie(ChatIdentity::COOKIE, chatVisitorToken($first))
        ->postJson('/api/v1/chat/stream', ['message' => 'again'])
        ->assertStatus(429)
        ->assertJsonPath('data.reason', 'budget_exceeded');
});
