<?php

namespace App\Http\Requests\V1\Shipping;

use App\Models\Measurement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;

/**
 * Shared plumbing for the shipping configuration endpoints.
 *
 * Unit ids are validated against the measurement they must belong to, so a
 * centimetre can never be stored as a weight, and code uniqueness is checked
 * inside the row's own tenant scope.
 */
abstract class ShippingConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return class-string<Model>
     */
    abstract protected function modelClass(): string;

    protected function measurementId(string $code): int
    {
        return (int) Measurement::query()->where('code', $code)->value('id');
    }

    protected function unitRule(string $measurementCode): Exists
    {
        $measurementId = $this->measurementId($measurementCode);

        return Rule::exists('units', 'id')
            ->where(fn ($query) => $query->where('measurement_id', $measurementId));
    }

    protected function uniqueCode(string $table, string $column = 'code', bool $ignoreCurrent = false): Unique
    {
        $rule = Rule::unique($table, $column)
            ->where(fn ($query) => $query->where('tenant_id', $this->tenantId()));

        if ($ignoreCurrent) {
            $uuid = $this->route('uuid');

            if (is_string($uuid) && $uuid !== '') {
                $rule->ignore($uuid, 'uuid');
            }
        }

        return $rule;
    }

    /**
     * The tenant the payload belongs to. Admins may target any tenant (or a
     * global row); merchants are pinned to their active membership.
     */
    protected function tenantId(): ?int
    {
        $user = $this->user('api');

        if ($user?->isAdmin()) {
            if ($this->has('tenant_id')) {
                return $this->input('tenant_id') !== null
                    ? (int) $this->input('tenant_id')
                    : null;
            }

            return $this->existingTenantId();
        }

        $tenantId = $user?->activeMemberships()->value('tenant_id');

        return $tenantId !== null ? (int) $tenantId : null;
    }

    protected function existingTenantId(): ?int
    {
        $uuid = $this->route('uuid');

        if (! is_string($uuid) || $uuid === '') {
            return null;
        }

        $class = $this->modelClass();
        $tenantId = $class::query()->where('uuid', $uuid)->value('tenant_id');

        return $tenantId !== null ? (int) $tenantId : null;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Give this record a name.',
            'code.alpha_dash' => 'The code may only contain letters, numbers, dashes and underscores.',
            'tenant_id.exists' => 'The selected tenant does not exist.',
        ];
    }
}
