<?php

declare(strict_types=1);

namespace App\Http\Controllers\V1\Chat;

use App\Enums\ChatMessageFeedbackEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Chat\StoreChatFeedbackRequest;
use App\Http\Responses\V1\ApiResponse;
use App\Models\ChatMessage;
use App\Support\Ai\ChatIdentity;
use Illuminate\Http\JsonResponse;

/**
 * Records a visitor's rating of a single reply.
 *
 * Feedback travels with the message row, which is what makes the improvement
 * loop work: an unhelpful reply is a signal to write a better KB entry.
 */
class ChatFeedbackController extends Controller
{
    public function store(StoreChatFeedbackRequest $request, ChatMessage $message): JsonResponse
    {
        $conversation = $message->conversation;

        if (! $conversation || $conversation->visitor_key !== ChatIdentity::visitorKey($request)) {
            return ApiResponse::error('Message not found.', null, 404);
        }

        $feedback = ChatMessageFeedbackEnum::from((string) $request->input('feedback'));

        $message->forceFill([
            'feedback' => $feedback->value,
            'feedback_comment' => $request->input('comment'),
            'feedback_at' => now(),
        ])->save();

        if ($feedback === ChatMessageFeedbackEnum::UNHELPFUL) {
            $conversation->forceFill(['needs_attention' => true])->save();
        }

        return ApiResponse::success(null, 'Thanks for the feedback.');
    }
}
