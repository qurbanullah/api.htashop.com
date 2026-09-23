<?php

use App\Models\ChatEvent;
use App\Models\ChatMessage;
use App\Models\KnowledgeEntry;
use App\Support\Ai\ChatIdentity;

it('streams a grounded reply as server-sent events and persists the transcript', function () {
    fakeChatStream('Hello there');

    KnowledgeEntry::create([
        'title' => 'Shipping policy',
        'body' => 'We ship to many countries. See the shipping policy page for destinations and lead times.',
        'status' => 'published',
        'locale' => '*',
    ]);

    $response = $this->postJson('/api/v1/chat/stream', [
        'message' => 'Do you ship abroad?',
        'locale' => 'en',
    ]);

    $response->assertOk();
    $content = $response->streamedContent();

    expect($content)
        ->toContain('event: meta')
        ->toContain('event: token')
        ->toContain('event: citations')
        ->toContain('event: done');

    expect(ChatMessage::where('role', 'user')->count())->toBe(1)
        ->and(ChatMessage::where('role', 'assistant')->count())->toBe(1);

    $assistant = ChatMessage::where('role', 'assistant')->firstOrFail();

    expect($assistant->content)->toBe('Hello there')
        ->and($assistant->prompt_tokens)->toBe(12)
        ->and($assistant->completion_tokens)->toBe(3)
        ->and($assistant->citations)->toBeArray()
        ->and($assistant->citations[0]['title'])->toBe('Shipping policy');
});

it('flags an ungrounded reply as a knowledge gap', function () {
    fakeChatStream('I am not sure.');

    $this->postJson('/api/v1/chat/stream', ['message' => 'something with no matching content'])
        ->assertOk()
        ->streamedContent();

    expect(ChatEvent::where('type', ChatEvent::TYPE_GAP_DETECTED)->count())->toBe(1);
});

it('reopens the visitor transcript', function () {
    fakeChatStream();

    $response = $this->postJson('/api/v1/chat/stream', ['message' => 'hello agent']);
    $response->assertOk()->streamedContent();

    $this->withCredentials()
        ->withUnencryptedCookie(ChatIdentity::COOKIE, chatVisitorToken($response))
        ->getJson('/api/v1/chat/conversation')
        ->assertOk()
        ->assertJsonPath('data.messages.0.role', 'user')
        ->assertJsonPath('data.messages.1.role', 'assistant');
});
