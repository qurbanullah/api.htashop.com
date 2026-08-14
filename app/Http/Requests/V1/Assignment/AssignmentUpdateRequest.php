<?php

namespace App\Http\Requests\V1\Assignment;

use App\Models\Assignment;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Variant;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class AssignmentUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tenant_id' => ['sometimes', 'integer', 'exists:tenants,id'],
            'organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
            'assignable_type' => ['sometimes', 'string', 'in:product,variant'],
            'assignable_uuid' => ['sometimes', 'string', 'max:36'],
            'role' => ['nullable', 'string', 'max:100'],
            'is_primary' => ['nullable', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    protected function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator): void {
            $assignment = $this->currentAssignment();

            if (!$assignment) {
                return;
            }

            $payload = $this->all();
            $tenantId = (int) data_get($payload, 'tenant_id', $assignment->tenant_id);
            $organizationId = array_key_exists('organization_id', $payload)
                ? data_get($payload, 'organization_id')
                : $assignment->organization_id;
            $assignableType = data_get($payload, 'assignable_type', $this->typeAlias($assignment->assignable_type));
            $assignableUuid = data_get($payload, 'assignable_uuid', data_get($assignment->assignable, 'uuid'));
            $assignable = $this->resolveAssignable($assignableType, $assignableUuid);

            if ($organizationId) {
                $organization = Organization::query()->find($organizationId);

                if ($organization && $organization->tenant_id !== $tenantId) {
                    $validator->errors()->add('organization_id', 'The selected organization does not belong to the selected tenant.');
                }
            }

            if ($assignable && $this->assignableTenantId($assignable) !== $tenantId) {
                $validator->errors()->add('assignable_uuid', 'The selected record does not belong to the selected tenant.');
            }
        });
    }

    private function currentAssignment(): ?Assignment
    {
        return Assignment::query()->with('assignable')->find($this->route('id'));
    }

    private function resolveAssignable(?string $type, ?string $uuid): Product|Variant|null
    {
        if (!$type || !$uuid) {
            return null;
        }

        return match ($type) {
            'product' => Product::query()->where('uuid', $uuid)->first(),
            'variant' => Variant::query()->where('uuid', $uuid)->first(),
            default => null,
        };
    }

    private function assignableTenantId(Product|Variant $assignable): int
    {
        return $assignable instanceof Product ? $assignable->tenant_id : $assignable->product->tenant_id;
    }

    private function typeAlias(string $modelClass): string
    {
        return match ($modelClass) {
            Product::class => 'product',
            Variant::class => 'variant',
            default => 'product',
        };
    }
}
