<?php

namespace App\Http\Requests\V1\Price;

use App\Models\Catalog;
use App\Models\Contract;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Variant;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class PriceStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tenant_id' => ['required', 'integer', 'exists:tenants,id'],
            'organization_id' => ['required', 'integer', 'exists:organizations,id'],
            'contract_id' => ['nullable', 'integer', 'exists:contracts,id'],
            'catalog_id' => ['nullable', 'integer', 'exists:catalogs,id'],
            'currency_id' => ['required', 'integer', 'exists:currencies,id'],
            'unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'priceable_type' => ['required', 'string', 'in:product,variant'],
            'priceable_uuid' => ['required', 'string', 'max:36'],
            'type' => ['nullable', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'min:0'],
            'min_quantity' => ['nullable', 'numeric', 'min:0.000001'],
            'max_quantity' => ['nullable', 'numeric', 'min:0.000001'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
            'priority' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    protected function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator): void {
            $tenantId = (int) $this->input('tenant_id');
            $organization = Organization::query()->find($this->input('organization_id'));
            $priceable = $this->resolvePriceable();
            $contract = $this->input('contract_id') ? Contract::query()->find($this->input('contract_id')) : null;
            $catalog = $this->input('catalog_id') ? Catalog::query()->find($this->input('catalog_id')) : null;

            if ($organization && $organization->tenant_id !== $tenantId) {
                $validator->errors()->add('organization_id', 'The selected organization does not belong to the selected tenant.');
            }

            if ($priceable && $this->priceableTenantId($priceable) !== $tenantId) {
                $validator->errors()->add('priceable_uuid', 'The selected record does not belong to the selected tenant.');
            }

            if ($priceable && $this->priceableOrganizationId($priceable) !== (int) $this->input('organization_id')) {
                $validator->errors()->add('organization_id', 'The selected organization must own the priced record.');
            }

            if ($contract && ($contract->tenant_id !== $tenantId || $contract->vendor_id !== (int) $this->input('organization_id'))) {
                $validator->errors()->add('contract_id', 'The selected contract is not valid for the selected tenant and organization.');
            }

            if ($catalog && ($catalog->tenant_id !== $tenantId || $catalog->organization_id !== (int) $this->input('organization_id'))) {
                $validator->errors()->add('catalog_id', 'The selected catalog is not valid for the selected tenant and organization.');
            }

            if ($this->filled('max_quantity') && (float) $this->input('max_quantity') < (float) $this->input('min_quantity', 1)) {
                $validator->errors()->add('max_quantity', 'The maximum quantity must be greater than or equal to the minimum quantity.');
            }
        });
    }

    private function resolvePriceable(): Product|Variant|null
    {
        return match ($this->input('priceable_type')) {
            'product' => Product::query()->where('uuid', $this->input('priceable_uuid'))->first(),
            'variant' => Variant::query()->where('uuid', $this->input('priceable_uuid'))->first(),
            default => null,
        };
    }

    private function priceableTenantId(Product|Variant $priceable): int
    {
        return $priceable instanceof Product ? $priceable->tenant_id : $priceable->product->tenant_id;
    }

    private function priceableOrganizationId(Product|Variant $priceable): int
    {
        return $priceable instanceof Product ? $priceable->organization_id : $priceable->product->organization_id;
    }
}
