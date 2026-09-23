<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ChatMessageFeedbackEnum;
use App\Enums\ChatRoleEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A single turn in a support-assistant conversation, with the usage and
 * grounding evidence needed for the improvement loop.
 */
class ChatMessage extends Model
{
    use HasFactory;

    protected $table = 'chat_messages';

    protected $fillable = [
        'uuid',
        'conversation_id',
        'role',
        'content',
        'citations',
        'tool_calls',
        'provider',
        'model',
        'prompt_tokens',
        'completion_tokens',
        'latency_ms',
        'feedback',
        'feedback_comment',
        'feedback_at',
    ];

    protected function casts(): array
    {
        return [
            'role' => ChatRoleEnum::class,
            'citations' => 'array',
            'tool_calls' => 'array',
            'prompt_tokens' => 'integer',
            'completion_tokens' => 'integer',
            'latency_ms' => 'integer',
            'feedback' => ChatMessageFeedbackEnum::class,
            'feedback_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ChatMessage $message): void {
            if (empty($message->uuid)) {
                $message->uuid = (string) Str::uuid();
            }
        });
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function totalTokens(): int
    {
        return (int) $this->prompt_tokens + (int) $this->completion_tokens;
    }
}
