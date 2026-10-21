<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

/**
 * @deprecated Use {@see Audit}. This class is retained as a backwards-compatible
 *             alias for older code/tests; it now reads and writes the canonical
 *             `audits` table. New code must use AuditLogger/Audit.
 */
class AuditLog extends Model
{
    use HasFactory;

    protected $table = 'audits';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'user_id',
        'actor_type',
        'actor_id',
        'event',
        'auditable_type',
        'auditable_id',
        'auditable_type_name',
        'old_values',
        'new_values',
        'changed_columns',
        'reason',
        'request_id',
        'ip_hash',
        'user_agent',
        'description',
        'occurred_at',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'changed_columns' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'occurred_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (AuditLog $auditLog): void {
            if (empty($auditLog->uuid)) {
                $auditLog->uuid = (string) Str::ulid();
            }

            if (empty($auditLog->occurred_at)) {
                $auditLog->occurred_at = now();
            }
        });
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function causer(): BelongsTo
    {
        return $this->user();
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

    public function getChangesAttribute(): array
    {
        if (! $this->old_values || ! $this->new_values) {
            return [];
        }

        $changes = [];

        foreach ($this->new_values as $key => $newValue) {
            $oldValue = $this->old_values[$key] ?? null;

            if ($oldValue !== $newValue) {
                $changes[$key] = [
                    'old' => $oldValue,
                    'new' => $newValue,
                ];
            }
        }

        return $changes;
    }

    public function getEventDescriptionAttribute(): string
    {
        $modelName = $this->auditable_type_name ?? class_basename($this->auditable_type);
        $userName = $this->user ? $this->user->name : 'System';

        return match ($this->event) {
            'created' => "{$userName} created {$modelName}",
            'updated' => "{$userName} updated {$modelName}",
            'deleted' => "{$userName} deleted {$modelName}",
            'restored' => "{$userName} restored {$modelName}",
            default => "{$userName} performed {$this->event} on {$modelName}",
        };
    }
}
