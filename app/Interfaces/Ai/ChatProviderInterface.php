<?php

declare(strict_types=1);

namespace App\Interfaces\Ai;

use App\Support\Ai\ChatStreamChunk;

/**
 * A chat-completion provider. Implementations must stream, so a slow provider
 * never blocks the visitor waiting for a full reply.
 *
 * Swapping DeepSeek for another provider (or a self-hosted model) is a config
 * change, not a code change — depend on this interface, never a concrete
 * client.
 */
interface ChatProviderInterface
{
    /**
     * Provider key, e.g. `deepseek`.
     */
    public function name(): string;

    /**
     * Model identifier sent to the provider, e.g. `deepseek-chat`.
     */
    public function model(): string;

    /**
     * Whether this provider has usable credentials.
     */
    public function isConfigured(): bool;

    /**
     * Stream a completion.
     *
     * @param  array<int, array<string, mixed>>  $messages  OpenAI-style messages
     * @param  array<int, array<string, mixed>>  $tools  OpenAI-style tool schemas
     * @param  array<string, mixed>  $options  {max_tokens, temperature}
     * @return iterable<ChatStreamChunk>
     *
     * @throws \RuntimeException when the provider rejects the request
     */
    public function stream(array $messages, array $tools = [], array $options = []): iterable;
}
