<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Chat;

use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Conversation
 */
class ConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'status' => $this->status->value,
            'locale' => $this->locale,
            'message_count' => (int) $this->message_count,
            'needs_attention' => (bool) $this->needs_attention,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
