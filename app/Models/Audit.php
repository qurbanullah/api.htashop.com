<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

/**
 * Canonical audit trail (hot mirror).
 *
 * Rows are append-only: application code creates them, never updates or deletes
 * them. The daily `audit:export` command copies them to object storage and
 * `audit:prune` removes only days that have a verified export.
 */
class Audit extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'tenant_id',
        'user_id',
        'actor_type',
        'actor_id',
        'acted_as_type',
        'acted_as_id',
        'event',
        'auditable_type',
        'auditable_id',
        'auditable_type_name',
        'meta',
        'old_values',
        'new_values',
        'changed_columns',
        'reason',
        'request_id',
        'ip_address',
        'ip_hash',
        'user_agent',
        'description',
        'occurred_at',
        'prev_hash',
        'hash',
    ];

    protected $casts = [
        'tenant_id' => 'integer',
        'actor_id' => 'integer',
        'acted_as_id' => 'integer',
        'auditable_id' => 'integer',
        'meta' => 'array',
        'old_values' => 'array',
        'new_values' => 'array',
        'changed_columns' => 'array',
        'occurred_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Audit $audit): void {
            if (empty($audit->uuid)) {
                $audit->uuid = (string) Str::ulid();
            }

            if (empty($audit->occurred_at)) {
                $audit->occurred_at = now();
            }

            if (empty($audit->actor_type)) {
                $audit->actor_type = $audit->user_id ? User::class : 'system';
                $audit->actor_id ??= $audit->user_id;
            }
        });
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Backwards-compatible alias expected by some controllers/frontend.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function actedAs(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'acted_as_type', 'acted_as_id');
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'auditable_type', 'auditable_id');
    }

    public function getActorUserIdAttribute(): ?int
    {
        return $this->user_id ?? ($this->actor_type === User::class ? $this->actor_id : null);
    }

    public function getTargetIdAttribute(): int|string|null
    {
        return $this->auditable_id;
    }

    public function scopeEvent($query, string $event)
    {
        return $query->where('event', $event);
    }

    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForModel($query, string $modelType)
    {
        return $query->where('auditable_type', $modelType);
    }

    public function scopeBetweenDates($query, mixed $from, mixed $to)
    {
        return $query
            ->when($from, fn ($inner) => $inner->where('occurred_at', '>=', $from))
            ->when($to, fn ($inner) => $inner->where('occurred_at', '<=', $to));
    }
}
