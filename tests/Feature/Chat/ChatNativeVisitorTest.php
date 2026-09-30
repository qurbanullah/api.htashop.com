<?php

use App\Models\Conversation;
use App\Support\Ai\ChatIdentity;
use Illuminate\Http\Request;

/**
 * Native shells cannot hold the cross-site `chat_visitor` cookie, so they send
 * the same opaque token in the X-Chat-Token header instead. Without that
 * fallback every device would hash an empty token onto one shared visitor key.
 */
function nativeVisitorToken(string $character = 'a'): string
{
    return str_repeat($character, 64);
}

it('accepts the visitor token from the header and issues no cookie', function () {
    $response = $this->withHeader(ChatIdentity::HEADER, nativeVisitorToken())
        ->getJson('/api/v1/chat/conversation');

    $response->assertOk();

    // The header is authoritative, so the browser cookie path must not run.
    expect(chatVisitorToken($response))->toBe('');
});

it('still issues a cookie when no header is sent', function () {
    $response = $this->getJson('/api/v1/chat/conversation');

    $response->assertOk();

    expect(chatVisitorToken($response))->toHaveLength(64);
});

it('ignores a malformed header and falls back to the cookie', function () {
    $response = $this->withHeader(ChatIdentity::HEADER, 'not-a-valid-token')
        ->getJson('/api/v1/chat/conversation');

    $response->assertOk();

    expect(chatVisitorToken($response))->toHaveLength(64);
});

it('derives a distinct visitor key per header token', function () {
    $keyFor = function (string $token): string {
        $request = Request::create('/api/v1/chat/conversation');
        $request->headers->set(ChatIdentity::HEADER, $token);

        return ChatIdentity::visitorKey($request);
    };

    expect($keyFor(nativeVisitorToken('a')))
        ->not->toBe($keyFor(nativeVisitorToken('b')))
        ->and($keyFor(nativeVisitorToken('a')))->toBe(hash('sha256', 'chat|'.nativeVisitorToken('a')));
});

it('keeps native transcripts separate per device', function () {
    fakeChatStream('First device reply');

    $this->withHeader(ChatIdentity::HEADER, nativeVisitorToken('a'))
        ->postJson('/api/v1/chat/stream', ['message' => 'hello'])
        ->assertOk()
        ->streamedContent();

    expect(Conversation::query()->pluck('visitor_key')->all())
        ->toBe([hash('sha256', 'chat|'.nativeVisitorToken('a'))]);

    fakeChatStream('Second device reply');

    $this->withHeader(ChatIdentity::HEADER, nativeVisitorToken('b'))
        ->postJson('/api/v1/chat/stream', ['message' => 'hello'])
        ->assertOk()
        ->streamedContent();

    expect(Conversation::query()->count())->toBe(2);
});

it('resumes the same thread for a repeated header token', function () {
    fakeChatStream('Remember me');

    $token = nativeVisitorToken();

    $this->withHeader(ChatIdentity::HEADER, $token)
        ->postJson('/api/v1/chat/stream', ['message' => 'hello'])
        ->assertOk()
        ->streamedContent();

    $this->withHeader(ChatIdentity::HEADER, $token)
        ->getJson('/api/v1/chat/conversation')
        ->assertOk()
        ->assertJsonPath('data.messages.0.role', 'user')
        ->assertJsonPath('data.messages.1.role', 'assistant');
});
