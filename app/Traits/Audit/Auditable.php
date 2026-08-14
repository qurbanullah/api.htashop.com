<?php

declare(strict_types=1);

namespace App\Traits\Audit;

use App\Models\Audit;
use App\Observers\AuditObserver;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait Auditable
{
    /**
     * Boot the auditable trait for a model.
     */
    public static function bootAuditable(): void
    {
        // Register the observer after model boot completes to avoid Laravel 13 booting recursion.
        static::booted(function (): void {
            static::observe(AuditObserver::class);
        });
    }

    /**
     * Get all audit logs for the model.
     */
    public function auditLogs(): MorphMany
    {
        return $this->morphMany(Audit::class, 'auditable')->orderBy('created_at', 'desc');
    }

    /**
     * Get audit logs for a specific event.
     */
    public function auditLogsForEvent(string $event): MorphMany
    {
        return $this->auditLogs()->where('event', $event);
    }

    /**
     * Get the latest audit log.
     */
    public function latestAuditLog()
    {
        return $this->auditLogs()->first();
    }

    /**
     * Get attributes that should be excluded from auditing.
     * Override this method in your model to customize.
     */
    public function getAuditExclude(): array
    {
        return [
            'created_at',
            'updated_at',
            'deleted_at',
        ];
    }

    /**
     * Get attributes that should be included in auditing.
     * If this returns a non-empty array, only these attributes will be audited.
     * Override this method in your model to customize.
     */
    public function getAuditInclude(): array
    {
        return [];
    }

    /**
     * Determine if audit logging is enabled for this model instance.
     * Override this method in your model to customize.
     */
    public function shouldAudit(): bool
    {
        return true;
    }

    /**
     * Get a custom description for the audit log entry.
     * Override this method in your model to customize.
     */
    public function getAuditDescription(string $event): ?string
    {
        return null;
    }
}
