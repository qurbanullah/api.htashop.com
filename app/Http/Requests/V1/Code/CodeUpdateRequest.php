<?php

namespace App\Http\Requests\V1\Code;

use App\Models\Code;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Variant;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class CodeUpdateRequest extends FormRequest
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
            'codeable_type' => ['sometimes', 'string', 'in:product,variant'],
            'codeable_uuid' => ['sometimes', 'string', 'max:36'],
            'type' => ['sometimes', 'string', 'max:100'],
            'value' => ['sometimes', 'string', 'max:255'],
            'context' => ['nullable', 'string', 'max:255'],
            'is_primary' => ['sometimes', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    protected function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator): void {
            $code = Code::query()->with('codeable')->find($this->route('id'));

            if (!$code) {
                return;
            }

            $tenantId = (int) ($this->input('tenant_id') ?? $code->tenant_id);
            $organizationId = $this->input('organization_id', $code->organization_id);
            $codeable = $this->resolveCodeable($code);

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

    private function resolveCodeable(Code $code): Product|Variant|null
    {
        $type = $this->input('codeable_type');
        $uuid = $this->input('codeable_uuid');

        if (!$type || !$uuid) {
            return $code->codeable;
        }

        return match ($type) {
            'product' => Product::query()->where('uuid', $uuid)->first(),
            'variant' => Variant::query()->where('uuid', $uuid)->first(),
            default => null,
        };
    }

    private function codeableTenantId(Product|Variant $codeable): int
    {
        return $codeable instanceof Product ? $codeable->tenant_id : $codeable->product->tenant_id;
    }
}
