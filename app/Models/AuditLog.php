<?php

declare(strict_types=1);

namespace App\Models;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AuditLog extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'audit_logs';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'auditable_type',
        'auditable_id',
        'user_id',
        'event',
        'auditable_type_name',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'description',
    ];

    /**
     * Attribute casting for Eloquent
     *
     * @var array<string,string>
     */
    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the auditable model (polymorphic relationship).
     */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the user who performed the action.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the causer (alias for user for compatibility).
     */
    public function causer(): BelongsTo
    {
        return $this->user();
    }

    /**
     * Scope to filter by event type.
     */
    public function scopeEvent($query, string $event)
    {
        return $query->where('event', $event);
    }

    /**
     * Scope to filter by user.
     */
    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to filter by auditable type.
     */
    public function scopeForModel($query, string $modelType)
    {
        return $query->where('auditable_type', $modelType);
    }

    /**
     * Get the changes made (difference between old and new values).
     */
    public function getChangesAttribute(): array
    {
        if (!$this->old_values || !$this->new_values) {
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

    /**
     * Get a human-readable description of the event.
     */
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
