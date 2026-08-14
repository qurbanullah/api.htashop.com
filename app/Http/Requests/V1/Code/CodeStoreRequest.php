<?php

namespace App\Http\Requests\V1\Code;

use App\Models\Organization;
use App\Models\Product;
use App\Models\Variant;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class CodeStoreRequest extends FormRequest
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
            'codeable_type' => ['required', 'string', 'in:product,variant'],
            'codeable_uuid' => ['required', 'string', 'max:36'],
            'type' => ['required', 'string', 'max:100'],
            'value' => ['required', 'string', 'max:255'],
            'context' => ['nullable', 'string', 'max:255'],
            'is_primary' => ['nullable', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    protected function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator): void {
            $tenantId = (int) $this->input('tenant_id');
            $organizationId = $this->input('organization_id');
            $codeable = $this->resolveCodeable();

            if ($organizationId) {
                $organization = Organization::query()->find($organizationId);

                if ($organization && $organization->tenant_id !== $tenantId) {
                    $validator->errors()->add('organization_id', 'The selected organization does not belong to the selected tenant.');
                }
            }

            if ($codeable && $this->codeableTenantId($codeable) !== $tenantId) {
                $validator->errors()->add('codeable_uuid', 'The selected record does not belong to the selected tenant.');
            }
        });
    }

    private function resolveCodeable(): Product|Variant|null
    {
        return match ($this->input('codeable_type')) {
            'product' => Product::query()->where('uuid', $this->input('codeable_uuid'))->first(),
            'variant' => Variant::query()->where('uuid', $this->input('codeable_uuid'))->first(),
            default => null,
        };
    }

    private function codeableTenantId(Product|Variant $codeable): int
    {
        return $codeable instanceof Product ? $codeable->tenant_id : $codeable->product->tenant_id;
    }
}
