<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Audit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditObserver
{
    /**
     * Handle the model "created" event.
     */
    public function created(Model $model): void
    {
        $this->logActivity($model, 'created', [], $this->getModelAttributes($model));
    }

    /**
     * Handle the model "updated" event.
     */
    public function updated(Model $model): void
    {
        $this->logActivity(
            $model,
            'updated',
            $this->getOriginalValues($model),
            $this->getModelAttributes($model)
        );
    }

    /**
     * Handle the model "deleted" event.
     */
    public function deleted(Model $model): void
    {
        $this->logActivity($model, 'deleted', $this->getModelAttributes($model), []);
    }

    /**
     * Handle the model "restored" event (for soft deletes).
     */
    public function restored(Model $model): void
    {
        $this->logActivity($model, 'restored', [], $this->getModelAttributes($model));
    }

    /**
     * Log the activity.
     */
    protected function logActivity(Model $model, string $event, array $oldValues, array $newValues): void
    {
        // Check if the model should be audited
        if (method_exists($model, 'shouldAudit') && !$model->shouldAudit()) {
            return;
        }

        // Get filtered attributes
        $oldValues = $this->filterAttributes($model, $oldValues);
        $newValues = $this->filterAttributes($model, $newValues);

        // Skip if no changes for update events
        if ($event === 'updated' && empty(array_diff_assoc($newValues, $oldValues))) {
            return;
        }

        // Get custom description if available
        $description = method_exists($model, 'getAuditDescription')
            ? $model->getAuditDescription($event)
            : null;

        // Create audit log entry
        Audit::create([
            'auditable_type' => get_class($model),
            'auditable_id' => $model->getKey(),
            'user_id' => Auth::id(),
            'event' => $event,
            'auditable_type_name' => class_basename($model),
            'old_values' => !empty($oldValues) ? $oldValues : null,
            'new_values' => !empty($newValues) ? $newValues : null,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'description' => $description,
        ]);
    }

    /**
     * Get the original values before the model was updated.
     */
    protected function getOriginalValues(Model $model): array
    {
        $original = [];

        foreach ($model->getDirty() as $key => $value) {
            $originalValue = $model->getOriginal($key);

            // Convert enum to string value
            if ($originalValue instanceof \BackedEnum) {
                $original[$key] = $originalValue->value;
            } elseif ($originalValue instanceof \UnitEnum) {
                $original[$key] = $originalValue->name;
            } else {
                $original[$key] = $originalValue;
            }
        }

        return $original;
    }

    /**
     * Get the current model attributes.
     */
    protected function getModelAttributes(Model $model): array
    {
        $attributes = $model->getAttributes();

        // Convert date attributes to string format
        foreach ($model->getDates() as $dateAttribute) {
            if (isset($attributes[$dateAttribute]) && $attributes[$dateAttribute] instanceof \DateTimeInterface) {
                $attributes[$dateAttribute] = $attributes[$dateAttribute]->toDateTimeString();
            }
        }

        // Convert enum attributes to their string values
        foreach ($attributes as $key => $value) {
            if ($value instanceof \BackedEnum) {
                $attributes[$key] = $value->value;
            } elseif ($value instanceof \UnitEnum) {
                $attributes[$key] = $value->name;
            }
        }

        return $attributes;
    }

    /**
     * Filter attributes based on model's audit configuration.
     */
    protected function filterAttributes(Model $model, array $attributes): array
    {
        // Get include list (if specified, only these will be audited)
        $include = method_exists($model, 'getAuditInclude')
            ? $model->getAuditInclude()
            : [];

        // Get exclude list
        $exclude = method_exists($model, 'getAuditExclude')
            ? $model->getAuditExclude()
            : ['created_at', 'updated_at', 'deleted_at'];

        // If include list is specified, only use those attributes
        if (!empty($include)) {
            $attributes = array_intersect_key($attributes, array_flip($include));
        }

        // Remove excluded attributes
        foreach ($exclude as $excludedKey) {
            unset($attributes[$excludedKey]);
        }

        return $attributes;
    }
}
