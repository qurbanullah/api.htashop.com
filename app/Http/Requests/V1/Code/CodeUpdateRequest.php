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

    /**
     * @return array<string, array<int, string>>
     */
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
            $routeId = $this->route()?->parameter('id');

            $code = Code::query()->with('codeable')->find($routeId);

            if (! $code) {
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

            if (($this->exists('codeable_type') || $this->exists('codeable_uuid')) && ! $codeable) {
                $validator->errors()->add('codeable_uuid', 'The selected record could not be found.');

                return;
            }

            if ($codeable && $this->codeableTenantId($codeable) !== $tenantId) {
                $validator->errors()->add('codeable_uuid', 'The selected record does not belong to the selected tenant.');
            }

            if ($codeable && $organizationId && $this->codeableOrganizationId($codeable) !== (int) $organizationId) {
                $validator->errors()->add('organization_id', 'The selected organization must own the coded record.');
            }
        });
    }

    private function resolveCodeable(Code $code): Product|Variant|null
    {
        if (! $this->exists('codeable_type') && ! $this->exists('codeable_uuid')) {
            return $code->codeable;
        }

        $type = $this->input('codeable_type', $this->typeAlias($code->codeable_type));
        $uuid = $this->input('codeable_uuid', $code->codeable?->uuid);

        if (! $type || ! $uuid) {
            return null;
        }

        return match ($type) {
            'product' => Product::query()->where('uuid', $uuid)->first(),
            'variant' => Variant::query()->where('uuid', $uuid)->first(),
            default => null,
        };
    }

    private function typeAlias(string $modelClass): ?string
    {
        return match ($modelClass) {
            Product::class => 'product',
            Variant::class => 'variant',
            default => null,
        };
    }

    private function codeableTenantId(Product|Variant $codeable): int
    {
        return $codeable instanceof Product ? $codeable->tenant_id : $codeable->product->tenant_id;
    }

    private function codeableOrganizationId(Product|Variant $codeable): int
    {
        return $codeable instanceof Product ? $codeable->organization_id : $codeable->product->organization_id;
    }
}
