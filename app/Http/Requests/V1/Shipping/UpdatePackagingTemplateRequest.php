<?php

namespace App\Http\Requests\V1\Shipping;

use App\Models\PackagingTemplate;

class UpdatePackagingTemplateRequest extends ShippingConfigRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'code' => [
                'sometimes',
                'nullable',
                'string',
                'max:80',
                'alpha_dash',
                $this->uniqueCode('packaging_templates', 'code', ignoreCurrent: true),
            ],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],

            'packaging_type_unit_id' => ['sometimes', 'nullable', 'integer', $this->unitRule('packaging')],

            'length' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100000'],
            'width' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100000'],
            'height' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100000'],
            'dimension_unit_id' => ['sometimes', 'nullable', 'integer', $this->unitRule('length')],

            'max_weight' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100000'],
            'weight_unit_id' => ['sometimes', 'nullable', 'integer', $this->unitRule('mass')],

            'tare_weight' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100000'],
            'tare_weight_unit_id' => ['sometimes', 'nullable', 'integer', $this->unitRule('mass')],

            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:65535'],
            'metadata' => ['sometimes', 'nullable', 'array'],
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
