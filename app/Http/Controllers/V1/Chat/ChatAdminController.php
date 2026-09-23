<?php

declare(strict_types=1);

namespace App\Http\Controllers\V1\Chat;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Chat\ChatConversationAdminCollection;
use App\Http\Resources\V1\Chat\ChatConversationAdminResource;
use App\Http\Resources\V1\Chat\ChatMessageAdminResource;
use App\Http\Responses\V1\ApiResponse;
use App\Models\Conversation;
use App\Services\Chat\ConversationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Admin review of assistant conversations: what visitors asked, what was
 * answered, what escalated, and what fell through the knowledge base.
 */
class ChatAdminController extends Controller
{
    public function __construct(
        private ConversationService $conversationService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $conversations = $this->conversationService
                ->search([
                    'search' => $request->input('search'),
                    'status' => $request->input('status'),
                    'needs_attention' => $request->input('needs_attention'),
                ])
                ->paginate(min(100, max(1, (int) $request->input('per_page', 15))));

            return ApiResponse::success(
                new ChatConversationAdminCollection($conversations),
                'Conversations retrieved successfully'
            );
        } catch (Throwable $throwable) {
            Log::error('Failed to list chat conversations', ['error' => $throwable->getMessage()]);

            return ApiResponse::error('Failed to retrieve conversations', null, 500);
        }
    }

    public function statistics(): JsonResponse
    {
        try {
            return ApiResponse::success(
                $this->conversationService->statistics(),
                'Chat statistics retrieved successfully'
            );
        } catch (Throwable $throwable) {
            Log::error('Failed to retrieve chat statistics', ['error' => $throwable->getMessage()]);

            return ApiResponse::error('Failed to retrieve chat statistics', null, 500);
        }
    }

    public function show(string $uuid): JsonResponse
    {
        $conversation = Conversation::query()
            ->with('user')
            ->where('uuid', $uuid)
            ->first();

        if (! $conversation) {
            return ApiResponse::error('Conversation not found', null, 404);
        }

        return ApiResponse::success([
            'conversation' => new ChatConversationAdminResource($conversation),
            'messages' => ChatMessageAdminResource::collection($conversation->messages()->get()),
        ], 'Conversation retrieved successfully');
    }
}
