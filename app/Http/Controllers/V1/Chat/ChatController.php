<?php

declare(strict_types=1);

namespace App\Http\Controllers\V1\Chat;

use App\Enums\ChatRoleEnum;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Chat\ChatMessageResource;
use App\Http\Resources\V1\Chat\ConversationResource;
use App\Http\Responses\V1\ApiResponse;
use App\Services\Ai\ProviderManager;
use App\Services\Chat\ConversationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Non-streaming chat endpoints: widget configuration, transcript restore and
 * starting a new conversation.
 */
class ChatController extends Controller
{
    public function __construct(
        protected ConversationService $conversations,
        protected ProviderManager $providers,
    ) {}

    /**
     * Widget configuration. Safe to call before any conversation exists.
     */
    public function config(Request $request): JsonResponse
    {
        return ApiResponse::success([
            'enabled' => (bool) config('ai.enabled', true),
            'preview' => (bool) config('ai.preview', false),
            'configured' => $this->providers->chat()->isConfigured(),
            'tickets_enabled' => (bool) config('ai.chat.tickets_enabled', true),
            'restore_transcript' => (bool) config('ai.chat.restore_transcript', true),
            'max_history_messages' => (int) config('ai.chat.max_history_messages', 10),
            'locale' => $this->locale($request),
            'greeting' => 'Hi! Ask me about orders, shipping, returns, or anything else about HTAShop.',
            'suggestions' => [
                'How do I track my order?',
                'Where can I read your refund policy?',
                'How do I contact support?',
            ],
        ]);
    }

    /**
     * The session's recent transcript, so the panel reopens where it left off.
     */
    public function show(Request $request): JsonResponse
    {
        if (! config('ai.chat.restore_transcript', true)) {
            return ApiResponse::success(['conversation' => null, 'messages' => []]);
        }

        $conversation = $this->conversations->current($request);

        if (! $conversation) {
            return ApiResponse::success(['conversation' => null, 'messages' => []]);
        }

        $messages = $conversation->messages()
            ->whereIn('role', [ChatRoleEnum::USER->value, ChatRoleEnum::ASSISTANT->value])
            // The relation already orders by id ascending, so replace it.
            ->reorder('id', 'desc')
            ->limit((int) config('ai.chat.max_history_messages', 10))
            ->get()
            ->reverse()
            ->values();

        return ApiResponse::success([
            'conversation' => new ConversationResource($conversation),
            'messages' => ChatMessageResource::collection($messages),
        ]);
    }

    /**
     * Abandon the current thread.
     */
    public function reset(Request $request): JsonResponse
    {
        $this->conversations->reset($request);

        return ApiResponse::success(null, 'Started a new conversation.');
    }

    protected function locale(Request $request): string
    {
        $locale = (string) $request->input('locale', app()->getLocale());

        return in_array($locale, ['en', 'de', 'ur'], true) ? $locale : 'en';
    }
}
