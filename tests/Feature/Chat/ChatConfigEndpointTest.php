<?php

it('returns the widget configuration', function () {
    $this->getJson('/api/v1/chat/config')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.enabled', true)
        ->assertJsonStructure([
            'data' => [
                'enabled',
                'preview',
                'configured',
                'tickets_enabled',
                'restore_transcript',
                'max_history_messages',
                'locale',
                'greeting',
                'suggestions',
            ],
        ]);
});

it('reports itself as disabled and refuses to stream when switched off', function () {
    config(['ai.enabled' => false]);

    $this->getJson('/api/v1/chat/config')
        ->assertOk()
        ->assertJsonPath('data.enabled', false);

    $this->postJson('/api/v1/chat/stream', ['message' => 'hello'])
        ->assertStatus(503)
        ->assertJsonPath('success', false);
});

it('returns an empty transcript before any conversation exists', function () {
    $this->getJson('/api/v1/chat/conversation')
        ->assertOk()
        ->assertJsonPath('data.conversation', null)
        ->assertJsonPath('data.messages', []);
});
