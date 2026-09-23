<?php

declare(strict_types=1);

namespace App\Interfaces\Ai;

use App\Support\Ai\ChatToolContext;

/**
 * A single capability the assistant may invoke while answering.
 *
 * Tools are strictly typed and side-effect-safe: they may read data or create
 * a ticket, but never run arbitrary queries or outbound requests derived from
 * model output.
 */
interface ChatToolInterface
{
    public function name(): string;

    public function description(): string;

    /**
     * JSON schema for the tool parameters (OpenAI `function.parameters`).
     *
     * @return array<string, mixed>
     */
    public function parameters(): array;

    /**
     * Execute the tool.
     *
     * @param  array<string, mixed>  $arguments  Validated arguments from the model
     * @return array<string, mixed> Result merged into the tool message content
     */
    public function handle(array $arguments, ChatToolContext $context): array;
}
