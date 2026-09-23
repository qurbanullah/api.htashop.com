<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Chat;

use App\Models\ChatMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ChatMessage
 */
class ChatMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'role' => $this->role->value,
            'content' => (string) $this->content,
            'citations' => $this->citations ?? [],
            'tool_calls' => $this->tool_calls ?? [],
            'feedback' => $this->feedback?->value,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
