<?php

use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Support\Ai\ChatIdentity;

it('records feedback left by the visitor on their own reply', function () {
    fakeChatStream();

    $stream = $this->postJson('/api/v1/chat/stream', ['message' => 'hello']);
    $stream->assertOk()->streamedContent();

    $message = ChatMessage::where('role', 'assistant')->firstOrFail();

    $this->withCredentials()
        ->withUnencryptedCookie(ChatIdentity::COOKIE, chatVisitorToken($stream))
        ->postJson("/api/v1/chat/messages/{$message->uuid}/actions/feedback", [
            'feedback' => 'unhelpful',
            'comment' => 'Did not answer my question',
        ])
        ->assertOk();

    $message->refresh();

    expect($message->feedback?->value)->toBe('unhelpful')
        ->and($message->feedback_comment)->toBe('Did not answer my question')
        ->and($message->conversation->needs_attention)->toBeTrue();
});

it('rejects feedback for a message belonging to another visitor', function () {
    $conversation = Conversation::create([
        'visitor_key' => hash('sha256', 'chat|someone-else'),
        'locale' => 'en',
        'status' => 'open',
    ]);

    $message = ChatMessage::create([
        'conversation_id' => $conversation->id,
        'role' => 'assistant',
        'content' => 'hi',
    ]);

    $this->postJson("/api/v1/chat/messages/{$message->uuid}/actions/feedback", [
        'feedback' => 'helpful',
    ])->assertStatus(404);
});
