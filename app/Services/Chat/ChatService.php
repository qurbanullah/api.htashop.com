<?php

declare(strict_types=1);

namespace App\Services\Chat;

use App\Enums\ChatRoleEnum;
use App\Interfaces\Ai\KnowledgeRetrieverInterface;
use App\Models\ChatEvent;
use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Models\User;
use App\Services\Ai\PromptBuilder;
use App\Services\Ai\ProviderManager;
use App\Services\Ai\TokenBudgetService;
use App\Services\Ai\ToolRegistry;
use App\Support\Ai\ChatStreamChunk;
use App\Support\Ai\ChatToolContext;
use Throwable;

/**
 * Orchestrates one assistant reply.
 *
 * Yields SSE-shaped frames so the transport stays a controller concern and the
 * whole flow — retrieval, prompting, tool calls, persistence, budgeting — is
 * unit-testable without a live model.
 */
class ChatService
{
    public function __construct(
        protected ProviderManager $providers,
        protected PromptBuilder $promptBuilder,
        protected ToolRegistry $tools,
        protected KnowledgeRetrieverInterface $retriever,
        protected TokenBudgetService $budget,
    ) {}

    /**
     * @return \Generator<int, array{event: string, data: array<string, mixed>}>
     */
    public function stream(Conversation $conversation, string $message, ?User $user = null): \Generator
    {
        try {
            $provider = $this->providers->chat();
        } catch (Throwable $exception) {
            logger()->error('Chat provider unavailable', ['error' => $exception->getMessage()]);

            yield $this->frame('error', ['message' => 'The assistant is not configured.']);

            return;
        }

        if (! $provider->isConfigured()) {
            yield $this->frame('error', ['message' => 'The assistant is not configured.']);

            return;
        }

        $locale = $conversation->locale ?: 'en';
        $startedAt = microtime(true);

        // Persist the visitor turn first, so the transcript is complete even if
        // the provider fails halfway through the reply.
        ChatMessage::create([
            'conversation_id' => $conversation->id,
            'role' => ChatRoleEnum::USER->value,
            'content' => $message,
        ]);
        $conversation->touchMessage();
        $this->record($conversation, ChatEvent::TYPE_MESSAGE_SENT, ['chars' => mb_strlen($message)]);

        yield $this->frame('meta', [
            'conversation_id' => $conversation->uuid,
            'model' => $provider->model(),
        ]);

        $passages = $this->retriever->retrieve(
            $message,
            $locale,
            (int) config('ai.retrieval.limit', 5)
        );
        $restricted = $this->isRestrictedTopic($message);

        $messages = $this->promptBuilder->build($conversation, $passages, $restricted, $locale);
        $toolSchemas = $this->tools->schemas();
        $context = new ChatToolContext($conversation, $user, $locale);

        $options = [
            'max_tokens' => (int) config('ai.chat.max_output_tokens', 800),
            'temperature' => (float) config('ai.chat.temperature', 0.2),
        ];

        $answer = '';
        $invokedTools = [];
        $usage = ['prompt_tokens' => 0, 'completion_tokens' => 0];
        $failed = false;

        try {
            $maxIterations = max(0, (int) config('ai.chat.max_tool_iterations', 3));

            for ($iteration = 0; $iteration <= $maxIterations; $iteration++) {
                $pending = [];

                foreach ($provider->stream($messages, $toolSchemas, $options) as $chunk) {
                    if ($chunk->type === ChatStreamChunk::TYPE_DELTA && $chunk->delta !== null) {
                        $answer .= $chunk->delta;

                        yield $this->frame('token', ['delta' => $chunk->delta]);
                    } elseif ($chunk->type === ChatStreamChunk::TYPE_TOOL_CALL && $chunk->toolCall !== null) {
                        $pending[] = $chunk->toolCall;
                    } elseif ($chunk->type === ChatStreamChunk::TYPE_USAGE && $chunk->usage !== null) {
                        $usage['prompt_tokens'] += (int) ($chunk->usage['prompt_tokens'] ?? 0);
                        $usage['completion_tokens'] += (int) ($chunk->usage['completion_tokens'] ?? 0);
                    }
                }

                if ($pending === []) {
                    break;
                }

                $messages[] = [
                    'role' => ChatRoleEnum::ASSISTANT->value,
                    'content' => null,
                    'tool_calls' => array_map(fn (array $call) => [
                        'id' => (string) ($call['id'] ?? ''),
                        'type' => 'function',
                        'function' => [
                            'name' => (string) ($call['name'] ?? ''),
                            'arguments' => json_encode($call['arguments'] ?? []),
                        ],
                    ], $pending),
                ];

                foreach ($pending as $call) {
                    $name = (string) ($call['name'] ?? '');
                    $role = 'done';

                    yield $this->frame('tool', ['name' => $name, 'status' => 'running']);

                    $result = $this->tools->execute($name, (array) ($call['arguments'] ?? []), $context);

                    if (isset($result['error'])) {
                        $role = 'error';
                    }

                    $invokedTools[] = ['name' => $name, 'status' => $role, 'arguments' => $call['arguments'] ?? []];
                    $messages[] = [
                        'role' => ChatRoleEnum::TOOL->value,
                        'tool_call_id' => (string) ($call['id'] ?? ''),
                        'content' => json_encode($result),
                    ];

                    $this->record($conversation, ChatEvent::TYPE_TOOL_INVOKED, ['tool' => $name, 'status' => $role]);

                    yield $this->frame('tool', ['name' => $name, 'status' => $role]);
                }
            }
        } catch (Throwable $exception) {
            $failed = true;

            logger()->error('Chat streaming failed', [
                'conversation' => $conversation->uuid,
                'error' => $exception->getMessage(),
            ]);
        }

        $citations = array_map(fn ($passage) => $passage->toCitation(), $passages);
        $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);

        $assistant = ChatMessage::create([
            'conversation_id' => $conversation->id,
            'role' => ChatRoleEnum::ASSISTANT->value,
            'content' => $answer !== '' ? $answer : null,
            'citations' => $citations !== [] ? $citations : null,
            'tool_calls' => $invokedTools !== [] ? $invokedTools : null,
            'provider' => $provider->name(),
            'model' => $provider->model(),
            'prompt_tokens' => $usage['prompt_tokens'],
            'completion_tokens' => $usage['completion_tokens'],
            'latency_ms' => $latencyMs,
        ]);
        $conversation->touchMessage();

        $this->budget->record(
            (string) $conversation->visitor_key,
            $usage['prompt_tokens'] + $usage['completion_tokens']
        );

        if (! $failed && $passages === [] && $invokedTools === []) {
            $this->flagGap($conversation, $message);
        }

        if ($failed) {
            yield $this->frame('error', [
                'message' => 'The assistant is temporarily unavailable. Please try again or contact support.',
            ]);
        }

        if ($citations !== []) {
            yield $this->frame('citations', ['items' => $citations]);
        }

        yield $this->frame('done', [
            'message_id' => $assistant->uuid,
            'usage' => $usage,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{event: string, data: array<string, mixed>}
     */
    protected function frame(string $event, array $data = []): array
    {
        return ['event' => $event, 'data' => $data];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function record(Conversation $conversation, string $type, array $payload = []): void
    {
        ChatEvent::create([
            'conversation_id' => $conversation->id,
            'type' => $type,
            'payload' => $payload,
        ]);
    }

    protected function isRestrictedTopic(string $message): bool
    {
        $haystack = mb_strtolower($message);

        foreach ((array) config('knowledge.restricted_topics', []) as $topic) {
            $topic = mb_strtolower(trim((string) $topic));

            if ($topic !== '' && str_contains($haystack, $topic)) {
                return true;
            }
        }

        return false;
    }

    /**
     * A reply with no grounding and no escalation is a knowledge gap: the
     * signal that turns traffic into content work.
     */
    protected function flagGap(Conversation $conversation, string $message): void
    {
        $conversation->forceFill(['needs_attention' => true])->save();

        $this->record($conversation, ChatEvent::TYPE_GAP_DETECTED, [
            'question' => mb_substr($message, 0, 500),
        ]);
    }
}
