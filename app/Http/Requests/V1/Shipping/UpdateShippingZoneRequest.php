<?php

namespace App\Http\Requests\V1\Shipping;

use App\Models\ShippingZone;

class UpdateShippingZoneRequest extends ShippingConfigRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'code' => [
                'sometimes',
                'nullable',
                'string',
                'max:80',
                'alpha_dash',
                $this->uniqueCode('shipping_zones', 'code', ignoreCurrent: true),
            ],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:65535'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ], $this->locationRules());
    }
}
