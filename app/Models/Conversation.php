<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ConversationStatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A support-assistant conversation. Guest-first: `user_id` is nullable and
 * the thread is tied to an anonymous `visitor_key`.
 */
class Conversation extends Model
{
    use HasFactory;

    protected $table = 'chat_conversations';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'user_id',
        'visitor_key',
        'locale',
        'status',
        'needs_attention',
        'summary',
        'ticket_id',
        'message_count',
        'last_message_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'status' => ConversationStatusEnum::class,
            'needs_attention' => 'boolean',
            'message_count' => 'integer',
            'last_message_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Conversation $conversation): void {
            if (empty($conversation->uuid)) {
                $conversation->uuid = (string) Str::uuid();
            }
        });
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class)->orderBy('id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Record that a message landed on this thread.
     */
    public function touchMessage(): void
    {
        $this->forceFill([
            'message_count' => $this->messages()->count(),
            'last_message_at' => now(),
        ])->save();
    }

    public function isEscalated(): bool
    {
        return $this->status === ConversationStatusEnum::ESCALATED;
    }
}
