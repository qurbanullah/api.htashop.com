<?php

declare(strict_types=1);

namespace App\Services\Ai\Tools;

use App\Enums\ConversationStatusEnum;
use App\Interfaces\Ai\ChatToolInterface;
use App\Models\ChatEvent;
use App\Services\Tickets\TicketService;
use App\Support\Ai\ChatToolContext;
use Illuminate\Support\Facades\Validator;

/**
 * Raises a real support ticket through the existing ticket pipeline, so the
 * assistant escalates instead of inventing an answer.
 */
class CreateSupportTicketTool implements ChatToolInterface
{
    public function __construct(
        protected TicketService $ticketService,
    ) {}

    public function name(): string
    {
        return 'create_support_ticket';
    }

    public function description(): string
    {
        return 'Raise a real support ticket for the visitor when their question cannot be '
            .'answered from the knowledge base, or when it is account-specific. Requires the '
            .'visitor\'s email address.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'subject' => ['type' => 'string', 'description' => 'Short summary of the request.'],
                'message' => ['type' => 'string', 'description' => 'Full detail of what the visitor needs.'],
                'email' => ['type' => 'string', 'description' => 'Visitor email for the reply.'],
            ],
            'required' => ['subject', 'message', 'email'],
        ];
    }

    public function handle(array $arguments, ChatToolContext $context): array
    {
        if (! config('ai.chat.tickets_enabled', true)) {
            return ['error' => 'Ticket creation is currently disabled.'];
        }

        $email = strtolower(trim((string) ($arguments['email'] ?? $context->user?->email ?? '')));
        $subject = trim((string) ($arguments['subject'] ?? ''));
        $message = trim((string) ($arguments['message'] ?? ''));

        $validator = Validator::make(
            ['email' => $email, 'subject' => $subject, 'message' => $message],
            [
                'email' => ['required', 'email'],
                'subject' => ['required', 'string', 'max:255'],
                'message' => ['required', 'string', 'min:10', 'max:10000'],
            ]
        );

        if ($validator->fails()) {
            return [
                'error' => 'The ticket could not be created.',
                'details' => $validator->errors()->toArray(),
            ];
        }

        $ticket = $this->ticketService->createPublicSupportTicket([
            'user_id' => $context->user?->id,
            'guest_name' => $context->user?->name ?? 'Website visitor',
            'guest_email' => $email,
            'title' => $subject,
            'stype' => 'question',
            'severity' => 'minor',
            'reproducibility' => 'not-applicable',
            'priority' => 'normal',
            'status' => 'open',
            'is_visible' => false,
            'description' => $message,
            'additional_information' => 'Created by the AI support assistant.',
        ]);

        $context->conversation->forceFill([
            'ticket_id' => $ticket->id,
            'status' => ConversationStatusEnum::ESCALATED->value,
            'needs_attention' => true,
        ])->save();

        ChatEvent::create([
            'conversation_id' => $context->conversation->id,
            'type' => ChatEvent::TYPE_ESCALATED,
            'payload' => ['ticket_id' => $ticket->id, 'source' => 'create_support_ticket'],
        ]);

        return [
            'status' => 'created',
            'ticket_number' => $ticket->ticket_number,
            'message' => 'A support ticket has been created. Our team will reply by email.',
        ];
    }
}
