<?php

use App\Enums\ChatRoleEnum;
use App\Http\Middleware\ApiAuthenticate;
use App\Models\ChatEvent;
use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('admin', 'api');
    $this->withoutMiddleware(ApiAuthenticate::class);
    Cache::flush();

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

function makeConversation(array $attributes = []): Conversation
{
    return Conversation::create(array_merge([
        'visitor_key' => hash('sha256', 'chat|'.uniqid('', true)),
        'locale' => 'en',
        'status' => 'open',
    ], $attributes));
}

it('lists conversations with a pagination envelope', function () {
    makeConversation(['needs_attention' => true]);

    $this->actingAs($this->admin)
        ->getJson('/api/v1/admin/chats')
        ->assertOk()
        ->assertJsonPath('data.pagination.total', 1)
        ->assertJsonPath('data.data.0.status', 'open')
        ->assertJsonPath('data.data.0.needs_attention', true);
});

it('filters conversations needing attention', function () {
    makeConversation(['needs_attention' => true]);
    makeConversation(['needs_attention' => false]);

    $this->actingAs($this->admin)
        ->getJson('/api/v1/admin/chats?needs_attention=1')
        ->assertOk()
        ->assertJsonPath('data.pagination.total', 1);
});

it('shows a conversation with its transcript in order', function () {
    $conversation = makeConversation();

    ChatMessage::create([
        'conversation_id' => $conversation->id,
        'role' => ChatRoleEnum::USER->value,
        'content' => 'Do you ship abroad?',
    ]);

    ChatMessage::create([
        'conversation_id' => $conversation->id,
        'role' => ChatRoleEnum::ASSISTANT->value,
        'content' => 'We ship to many countries.',
        'citations' => [['entry_id' => 1, 'uuid' => 'e1', 'title' => 'Shipping', 'url' => '/x', 'score' => 1]],
        'feedback' => 'helpful',
        'provider' => 'deepseek',
        'model' => 'deepseek-chat',
        'prompt_tokens' => 10,
        'completion_tokens' => 4,
        'latency_ms' => 900,
    ]);

    $this->actingAs($this->admin)
        ->getJson("/api/v1/admin/chats/{$conversation->uuid}")
        ->assertOk()
        ->assertJsonPath('data.conversation.uuid', $conversation->uuid)
        ->assertJsonPath('data.messages.0.role', 'user')
        ->assertJsonPath('data.messages.1.role', 'assistant')
        ->assertJsonPath('data.messages.1.feedback', 'helpful')
        ->assertJsonPath('data.messages.1.prompt_tokens', 10)
        ->assertJsonPath('data.messages.1.citations.0.title', 'Shipping');
});

it('returns 404 for an unknown conversation', function () {
    $this->actingAs($this->admin)
        ->getJson('/api/v1/admin/chats/00000000-0000-0000-0000-000000000000')
        ->assertStatus(404);
});

it('returns chat statistics', function () {
    $conversation = makeConversation(['status' => 'escalated', 'needs_attention' => true]);

    ChatMessage::create([
        'conversation_id' => $conversation->id,
        'role' => ChatRoleEnum::USER->value,
        'content' => 'hello',
    ]);
    ChatMessage::create([
        'conversation_id' => $conversation->id,
        'role' => ChatRoleEnum::ASSISTANT->value,
        'content' => 'hi',
        'feedback' => 'unhelpful',
    ]);

    ChatEvent::create([
        'conversation_id' => $conversation->id,
        'type' => ChatEvent::TYPE_GAP_DETECTED,
        'payload' => ['question' => 'unknown'],
    ]);

    $this->actingAs($this->admin)
        ->getJson('/api/v1/admin/chats/statistics')
        ->assertOk()
        ->assertJsonPath('data.total', 1)
        ->assertJsonPath('data.escalated', 1)
        ->assertJsonPath('data.needs_attention', 1)
        ->assertJsonPath('data.messages', 2)
        ->assertJsonPath('data.unhelpful', 1)
        ->assertJsonPath('data.gaps', 1);
});

it('forbids non-admin users from reading conversations', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/api/v1/admin/chats')
        ->assertStatus(403);
});
