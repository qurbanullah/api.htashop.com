<?php

namespace App\Http\Requests\V1\Price;

use App\Models\Catalog;
use App\Models\Contract;
use App\Models\Organization;
use App\Models\Price;
use App\Models\Product;
use App\Models\Variant;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class PriceUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tenant_id' => ['sometimes', 'integer', 'exists:tenants,id'],
            'organization_id' => ['sometimes', 'integer', 'exists:organizations,id'],
            'contract_id' => ['nullable', 'integer', 'exists:contracts,id'],
            'catalog_id' => ['nullable', 'integer', 'exists:catalogs,id'],
            'currency_id' => ['sometimes', 'integer', 'exists:currencies,id'],
            'unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'priceable_type' => ['sometimes', 'string', 'in:product,variant'],
            'priceable_uuid' => ['sometimes', 'string', 'max:36'],
            'type' => ['sometimes', 'string', 'max:100'],
            'base_price' => ['sometimes', 'numeric', 'min:0'],
            'min_quantity' => ['sometimes', 'numeric', 'min:0.000001'],
            'max_quantity' => ['nullable', 'numeric', 'min:0.000001'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
            'priority' => ['sometimes', 'integer'],
            'is_active' => ['sometimes', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    protected function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator): void {
            $price = Price::query()->with('priceable')->find($this->route('id'));

            if (!$price) {
                return;
            }

            $tenantId = (int) ($this->input('tenant_id') ?? $price->tenant_id);
            $organizationId = (int) ($this->input('organization_id') ?? $price->organization_id);
            $organization = Organization::query()->find($organizationId);
            $priceable = $this->resolvePriceable($price);
            $contract = $this->filled('contract_id') ? Contract::query()->find($this->input('contract_id')) : ($price->contract_id ? Contract::query()->find($price->contract_id) : null);
            $catalog = $this->filled('catalog_id') ? Catalog::query()->find($this->input('catalog_id')) : ($price->catalog_id ? Catalog::query()->find($price->catalog_id) : null);

            if ($organization && $organization->tenant_id !== $tenantId) {
                $validator->errors()->add('organization_id', 'The selected organization does not belong to the selected tenant.');
            }

            if ($priceable && $this->priceableTenantId($priceable) !== $tenantId) {
                $validator->errors()->add('priceable_uuid', 'The selected record does not belong to the selected tenant.');
            }

            if ($priceable && $this->priceableOrganizationId($priceable) !== $organizationId) {
                $validator->errors()->add('organization_id', 'The selected organization must own the priced record.');
            }

            if ($contract && ($contract->tenant_id !== $tenantId || $contract->vendor_id !== $organizationId)) {
                $validator->errors()->add('contract_id', 'The selected contract is not valid for the selected tenant and organization.');
            }

            if ($catalog && ($catalog->tenant_id !== $tenantId || $catalog->organization_id !== $organizationId)) {
                $validator->errors()->add('catalog_id', 'The selected catalog is not valid for the selected tenant and organization.');
            }
        });
    }

    private function resolvePriceable(Price $price): Product|Variant|null
    {
        $type = $this->input('priceable_type');
        $uuid = $this->input('priceable_uuid');

        if (!$type || !$uuid) {
            return $price->priceable;
        }

        return match ($type) {
            'product' => Product::query()->where('uuid', $uuid)->first(),
            'variant' => Variant::query()->where('uuid', $uuid)->first(),
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
