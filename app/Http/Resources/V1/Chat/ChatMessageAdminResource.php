<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Chat;

use App\Models\ChatMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Admin view of a message, including the usage and model metadata that the
 * public transcript deliberately omits.
 *
 * @mixin ChatMessage
 */
class ChatMessageAdminResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'role' => $this->role?->value,
            'content' => $this->content,
            'citations' => $this->citations ?? [],
            'tool_calls' => $this->tool_calls ?? [],
            'feedback' => $this->feedback?->value,
            'feedback_comment' => $this->feedback_comment,
            'provider' => $this->provider,
            'model' => $this->model,
            'prompt_tokens' => (int) $this->prompt_tokens,
            'completion_tokens' => (int) $this->completion_tokens,
            'latency_ms' => (int) $this->latency_ms,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
