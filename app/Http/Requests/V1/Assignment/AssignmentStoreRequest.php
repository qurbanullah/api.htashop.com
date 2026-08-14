<?php

namespace App\Http\Requests\V1\Assignment;

use App\Models\Organization;
use App\Models\Product;
use App\Models\Variant;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class AssignmentStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tenant_id' => ['required', 'integer', 'exists:tenants,id'],
            'organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
            'assignable_type' => ['required', 'string', 'in:product,variant'],
            'assignable_uuid' => ['required', 'string', 'max:36'],
            'role' => ['nullable', 'string', 'max:100'],
            'is_primary' => ['nullable', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    protected function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator): void {
            $tenantId = (int) $this->input('tenant_id');
            $organizationId = $this->input('organization_id');
            $assignable = $this->resolveAssignable($this->input('assignable_type'), $this->input('assignable_uuid'));

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
}
