<?php

declare(strict_types=1);

namespace App\Support\Ai;

/**
 * One chunk emitted while streaming a chat completion.
 *
 * A provider yields these; the chat service turns them into SSE frames and
 * accumulates usage/tool-calls. Immutable by design so it is safe to hold.
 */
final class ChatStreamChunk
{
    public const TYPE_DELTA = 'delta';

    public const TYPE_TOOL_CALL = 'tool_call';

    public const TYPE_USAGE = 'usage';

    public const TYPE_FINISH = 'finish';

    /**
     * @param  array<string, mixed>|null  $toolCall  {id, name, arguments}
     * @param  array<string, int>|null  $usage  {prompt_tokens, completion_tokens}
     */
    public function __construct(
        public readonly string $type,
        public readonly ?string $delta = null,
        public readonly ?array $toolCall = null,
        public readonly ?array $usage = null,
        public readonly ?string $finishReason = null,
    ) {}

    public static function delta(string $delta): self
    {
        return new self(self::TYPE_DELTA, delta: $delta);
    }

    /**
     * @param  array<string, mixed>  $toolCall
     */
    public static function toolCall(array $toolCall): self
    {
        return new self(self::TYPE_TOOL_CALL, toolCall: $toolCall);
    }

    /**
     * @param  array<string, int>  $usage
     */
    public static function usage(array $usage): self
    {
        return new self(self::TYPE_USAGE, usage: $usage);
    }

    public static function finish(?string $reason = null): self
    {
        return new self(self::TYPE_FINISH, finishReason: $reason);
    }
}
