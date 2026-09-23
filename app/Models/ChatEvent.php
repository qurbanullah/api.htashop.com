<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only analytics signal for the support assistant.
 */
class ChatEvent extends Model
{
    use HasFactory;

    protected $table = 'chat_events';

    public const TYPE_MESSAGE_SENT = 'message_sent';

    public const TYPE_ESCALATED = 'escalated';

    public const TYPE_RATE_LIMITED = 'rate_limited';

    public const TYPE_TOOL_INVOKED = 'tool_invoked';

    public const TYPE_GAP_DETECTED = 'gap_detected';

    protected $fillable = [
        'conversation_id',
        'type',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
