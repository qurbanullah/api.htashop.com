<?php

namespace App\Http\Requests\V1\Product;

use App\Models\Organization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;

class ProductStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
            'organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'seller_sku' => ['nullable', 'string', 'max:100'],
            'part_number' => ['nullable', 'string', 'max:100'],
            'hs_code' => ['nullable', 'string', 'max:20'],
            'unspsc' => ['nullable', 'string', 'max:20'],
            'ntn' => ['nullable', 'string', 'max:50'],
            'barcode' => ['nullable', 'string', 'max:100'],
            'model_number' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:100'],
            'summary' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'metadata' => ['nullable', 'array'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
            'feature_ids' => ['nullable', 'array'],
            'feature_ids.*' => ['integer', 'exists:features,id'],
            'features' => ['nullable', 'array'],
            'features.*' => ['string', 'max:255'],
            'manufacturer_ids' => ['nullable', 'array'],
            'manufacturer_ids.*' => ['integer', 'exists:manufacturers,id'],
            'brand_ids' => ['nullable', 'array'],
            'brand_ids.*' => ['integer', 'exists:brands,id'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'exists:tags,id'],
        ];
    }

    protected function passedValidation(): void
    {
        // Auto-resolve tenant/organization from user's active membership
        if (!$this->input('tenant_id') || !$this->input('organization_id')) {
            $user = $this->user();
            $membership = $user?->memberships()->where('is_active', true)->first();

            if ($membership) {
                $this->merge([
                    'tenant_id' => $this->input('tenant_id') ?: $membership->tenant_id,
                    'organization_id' => $this->input('organization_id') ?: $membership->organization_id,
                ]);
            }
        }
    }

    protected function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator): void {
            $tenantId = $this->input('tenant_id');
            $organizationId = $this->input('organization_id');

            if ($tenantId && $organizationId) {
                $organization = Organization::query()->find($organizationId);
                if ($organization && $organization->tenant_id !== (int) $tenantId) {
                    $validator->errors()->add('organization_id', 'The selected organization does not belong to the selected tenant.');
                }
            }
        });
    }
}
