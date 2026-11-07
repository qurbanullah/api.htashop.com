<?php

namespace App\Http\Requests\V1\Shipping;

use App\Models\ShippingZone;

class StoreShippingZoneRequest extends ShippingConfigRequest
{
    use ValidatesShippingZoneLocations;

    protected function modelClass(): string
    {
        return ShippingZone::class;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge([
            'name' => ['required', 'string', 'max:150'],
            'code' => ['nullable', 'string', 'max:80', 'alpha_dash', $this->uniqueCode('shipping_zones')],
            'description' => ['nullable', 'string', 'max:2000'],
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'metadata' => ['nullable', 'array'],
        ], $this->locationRules());
    }
}
