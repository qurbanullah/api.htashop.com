<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PunchoutSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'tenant_id',
        'buyer_id',
        'protocol',
        'status',
        'token',
        'token_hash',
        'access_token_hash',
        'handoff_code_hash',
        'handoff_code_expires_at',
        'handoff_code_used_at',
        'buyer_cookie',
        'return_url',
        'setup_payload',
        'cart_items',
        'cart_payload',
        'expires_at',
        'idle_expires_at',
        'started_at',
        'completed_at',
        'cancelled_at',
        'cart_returned_at',
        'last_activity_at',
    ];

    protected $casts = [
        'setup_payload' => 'array',
        'cart_items' => 'array',
        'cart_payload' => 'array',
        'expires_at' => 'datetime',
        'idle_expires_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'handoff_code_expires_at' => 'datetime',
        'handoff_code_used_at' => 'datetime',
        'cart_returned_at' => 'datetime',
        'last_activity_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (PunchoutSession $punchoutSession): void {
            if (empty($punchoutSession->uuid)) {
                $punchoutSession->uuid = (string) Str::uuid();
            }

            if (empty($punchoutSession->token)) {
                $punchoutSession->token = Str::random(64);
            }

            // Always keep a hash beside the legacy token. Resolution uses the
            // hash so a database leak does not expose live capability tokens.
            if (empty($punchoutSession->token_hash)) {
                $punchoutSession->token_hash = hash('sha256', $punchoutSession->token);
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(PunchoutBuyer::class, 'buyer_id');
    }

    public function isExpired(): bool
    {
        return ($this->expires_at !== null && $this->expires_at->isPast())
            || ($this->idle_expires_at !== null && $this->idle_expires_at->isPast());
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }
}
