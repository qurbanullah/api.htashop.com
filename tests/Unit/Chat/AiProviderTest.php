<?php

use App\Services\Ai\Providers\OpenAiCompatibleChatProvider;
use App\Services\Ai\Providers\OpenAiCompatibleEmbeddingProvider;
use App\Support\Ai\ChatStreamChunk;
use Illuminate\Support\Facades\Http;

function sseFrame(array $delta, ?string $finishReason = null, array $usage = []): string
{
    $payload = ['choices' => [['delta' => $delta, 'finish_reason' => $finishReason]]];

    if ($usage !== []) {
        $payload['usage'] = $usage;
    }

    return 'data: ' . json_encode($payload);
}

function chatProvider(): OpenAiCompatibleChatProvider
{
    return new OpenAiCompatibleChatProvider('deepseek', [
        'base_url' => 'https://api.deepseek.com',
        'api_key' => 'test-key',
        'model' => 'deepseek-chat',
    ]);
}

it('streams text deltas, usage and finish from an OpenAI-compatible response', function () {
    $body = implode("\n\n", [
        sseFrame(['content' => 'Hello']),
        sseFrame(['content' => ' there']),
        sseFrame([], 'stop'),
        'data: ' . json_encode(['choices' => [], 'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 2]]),
        'data: [DONE]',
    ]) . "\n\n";

    Http::fake(['*' => Http::response($body, 200)]);

    $chunks = iterator_to_array(chatProvider()->stream([['role' => 'user', 'content' => 'hi']]));

    $deltas = array_values(array_filter($chunks, fn ($c) => $c->type === ChatStreamChunk::TYPE_DELTA));
    expect(array_map(fn ($c) => $c->delta, $deltas))->toBe(['Hello', ' there']);

    $usage = array_values(array_filter($chunks, fn ($c) => $c->type === ChatStreamChunk::TYPE_USAGE));
    expect($usage)->toHaveCount(1)
        ->and($usage[0]->usage)->toBe(['prompt_tokens' => 10, 'completion_tokens' => 2]);

    $finish = array_values(array_filter($chunks, fn ($c) => $c->type === ChatStreamChunk::TYPE_FINISH));
    expect($finish)->toHaveCount(1)
        ->and($finish[0]->finishReason)->toBe('stop');
});

it('assembles streamed tool-call fragments into a complete call', function () {
    $body = implode("\n\n", [
        sseFrame(['tool_calls' => [[
            'index' => 0,
            'id' => 'call_1',
            'function' => ['name' => 'search_knowledge_base', 'arguments' => '{"qu'],
        ]]]),
        sseFrame(['tool_calls' => [[
            'index' => 0,
            'function' => ['arguments' => 'ery":"returns"}'],
        ]]]),
        sseFrame([], 'tool_calls'),
        'data: [DONE]',
    ]) . "\n\n";

    Http::fake(['*' => Http::response($body, 200)]);

    $chunks = iterator_to_array(chatProvider()->stream([['role' => 'user', 'content' => 'hi']], [
        ['type' => 'function', 'function' => ['name' => 'search_knowledge_base']],
    ]));

    $toolCalls = array_values(array_filter($chunks, fn ($c) => $c->type === ChatStreamChunk::TYPE_TOOL_CALL));

    expect($toolCalls)->toHaveCount(1)
        ->and($toolCalls[0]->toolCall['name'])->toBe('search_knowledge_base')
        ->and($toolCalls[0]->toolCall['arguments'])->toBe(['query' => 'returns']);
});

it('throws when the provider returns an error status', function () {
    Http::fake(['*' => Http::response(['error' => 'nope'], 401)]);

    expect(fn () => iterator_to_array(chatProvider()->stream([['role' => 'user', 'content' => 'hi']])))
        ->toThrow(RuntimeException::class);
});

it('maps embedding vectors back to input order', function () {
    Http::fake(['*' => Http::response([
        'data' => [
            ['index' => 1, 'embedding' => [0.2, 0.3]],
            ['index' => 0, 'embedding' => [0.0, 0.1]],
        ],
    ], 200)]);

    $provider = new OpenAiCompatibleEmbeddingProvider([
        'enabled' => true,
        'api_key' => 'test-key',
        'base_url' => 'https://api.openai.com/v1',
        'model' => 'text-embedding-3-small',
        'dimensions' => 2,
        'batch_size' => 64,
    ]);

    expect($provider->embed(['a', 'b']))->toBe([[0.0, 0.1], [0.2, 0.3]]);
});

it('reports embeddings as disabled without a key', function () {
    $provider = new OpenAiCompatibleEmbeddingProvider([
        'enabled' => true,
        'api_key' => '',
    ]);

    expect($provider->isEnabled())->toBeFalse();
});
