<?php

declare(strict_types=1);

namespace App\Services\Ai\Tools;

use App\Enums\ConversationStatusEnum;
use App\Interfaces\Ai\ChatToolInterface;
use App\Models\ChatEvent;
use App\Support\Ai\ChatToolContext;

/**
 * Flags the conversation for a human when the visitor asks for one.
 */
class EscalateToHumanTool implements ChatToolInterface
{
    public function name(): string
    {
        return 'escalate_to_human';
    }

    public function description(): string
    {
        return 'Flag this conversation for a human agent when the visitor asks to speak to '
            .'someone, or when the request is clearly outside the assistant\'s scope.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'reason' => [
                    'type' => 'string',
                    'description' => 'Why a human is needed.',
                ],
            ],
            'required' => ['reason'],
        ];
    }

    public function handle(array $arguments, ChatToolContext $context): array
    {
        $reason = trim((string) ($arguments['reason'] ?? 'Visitor requested a human agent.'));

        $context->conversation->forceFill([
            'status' => ConversationStatusEnum::ESCALATED->value,
            'needs_attention' => true,
        ])->save();

        ChatEvent::create([
            'conversation_id' => $context->conversation->id,
            'type' => ChatEvent::TYPE_ESCALATED,
            'payload' => ['reason' => $reason, 'source' => 'escalate_to_human'],
        ]);

        return [
            'status' => 'escalated',
            'message' => 'This conversation has been flagged for a human agent.',
        ];
    }
}
