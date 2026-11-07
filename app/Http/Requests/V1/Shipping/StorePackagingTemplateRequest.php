<?php

namespace App\Http\Requests\V1\Shipping;

use App\Models\PackagingTemplate;

class StorePackagingTemplateRequest extends ShippingConfigRequest
{
    protected function modelClass(): string
    {
        return PackagingTemplate::class;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'code' => ['nullable', 'string', 'max:80', 'alpha_dash', $this->uniqueCode('packaging_templates')],
            'description' => ['nullable', 'string', 'max:2000'],

            'packaging_type_unit_id' => ['nullable', 'integer', $this->unitRule('packaging')],

            'length' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'width' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'height' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'dimension_unit_id' => ['nullable', 'integer', $this->unitRule('length')],

            'max_weight' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'weight_unit_id' => ['nullable', 'integer', $this->unitRule('mass')],

            'tare_weight' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'tare_weight_unit_id' => ['nullable', 'integer', $this->unitRule('mass')],

            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'packaging_type_unit_id.exists' => 'Choose a packaging type (box, carton, pallet…).',
            'dimension_unit_id.exists' => 'Choose a unit of length for the dimensions.',
            'weight_unit_id.exists' => 'Choose a unit of mass for the maximum weight.',
            'tare_weight_unit_id.exists' => 'Choose a unit of mass for the tare weight.',
        ]);
    }
}
