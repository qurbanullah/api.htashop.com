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
        'protocol',
        'status',
        'token',
        'buyer_cookie',
        'return_url',
        'setup_payload',
        'cart_items',
        'cart_payload',
        'expires_at',
        'started_at',
        'completed_at',
        'cart_returned_at',
        'last_activity_at',
    ];

    protected $casts = [
        'setup_payload' => 'array',
        'cart_items' => 'array',
        'cart_payload' => 'array',
        'expires_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
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
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
