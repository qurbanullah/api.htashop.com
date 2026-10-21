<?php

declare(strict_types=1);

namespace App\Observers;

use App\Services\Audit\AuditLogger;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Converts Eloquent model events into audit rows through the single
 * {@see AuditLogger}, which applies redaction, IP hashing and request context.
 */
class AuditObserver
{
    public function __construct(
        protected AuditLogger $auditLogger,
    ) {}

    public function created(Model $model): void
    {
        $this->logActivity($model, 'created', [], $this->getModelAttributes($model));
    }

    public function updated(Model $model): void
    {
        $this->logActivity(
            $model,
            'updated',
            $this->getOriginalValues($model),
            $this->getModelAttributes($model),
        );
    }

    public function deleted(Model $model): void
    {
        $this->logActivity($model, 'deleted', $this->getModelAttributes($model), []);
    }

    public function restored(Model $model): void
    {
        $this->logActivity($model, 'restored', [], $this->getModelAttributes($model));
    }

    protected function logActivity(Model $model, string $event, array $oldValues, array $newValues): void
    {
        if (method_exists($model, 'shouldAudit') && ! $model->shouldAudit()) {
            return;
        }

        $oldValues = $this->filterAttributes($model, $oldValues);
        $newValues = $this->filterAttributes($model, $newValues);

        // An "updated" event with no audited column changes is noise (e.g.
        // `updated_at` only).
        if ($event === 'updated' && $this->sameValues($oldValues, $newValues)) {
            return;
        }

        $description = method_exists($model, 'getAuditDescription')
            ? $model->getAuditDescription($event)
            : null;

        $this->auditLogger->record(
            Auth::guard('api')->user() ?? Auth::user(),
            $event,
            $model,
            [
                'old_values' => $oldValues ?: null,
                'new_values' => $newValues ?: null,
                'description' => $description,
            ],
        );
    }

    /**
     * Get the original values before the model was updated.
     */
    protected function getOriginalValues(Model $model): array
    {
        $original = [];

        foreach ($model->getDirty() as $key => $value) {
            $original[$key] = $this->normaliseValue($model->getOriginal($key));
        }

        return $original;
    }

    /**
     * Get the current model attributes.
     */
    protected function getModelAttributes(Model $model): array
    {
        $attributes = [];

        foreach ($model->getAttributes() as $key => $value) {
            $attributes[$key] = $this->normaliseValue($value);
        }

        return $attributes;
    }

    protected function normaliseValue(mixed $value): mixed
    {
        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if ($value instanceof UnitEnum) {
            return $value->name;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        return $value;
    }

    /**
     * Filter attributes based on the model's audit configuration.
     */
    protected function filterAttributes(Model $model, array $attributes): array
    {
        $include = method_exists($model, 'getAuditInclude')
            ? $model->getAuditInclude()
            : [];

        $exclude = method_exists($model, 'getAuditExclude')
            ? $model->getAuditExclude()
            : ['created_at', 'updated_at', 'deleted_at'];

        if (! empty($include)) {
            $attributes = array_intersect_key($attributes, array_flip($include));
        }

        foreach ($exclude as $excludedKey) {
            unset($attributes[$excludedKey]);
        }

        return $attributes;
    }

    protected function sameValues(array $oldValues, array $newValues): bool
    {
        $keys = array_unique(array_merge(array_keys($oldValues), array_keys($newValues)));

        foreach ($keys as $key) {
            if (($oldValues[$key] ?? null) !== ($newValues[$key] ?? null)) {
                return false;
            }
        }

        return true;
    }
}
