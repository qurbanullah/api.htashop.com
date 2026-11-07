<?php

namespace App\Http\Requests\V1\Product;

use App\Models\Definition;
use App\Models\Measurement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductShippingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $massId = $this->measurementId('mass');
        $lengthId = $this->measurementId('length');
        $packagingId = $this->measurementId('packaging');

        return [
            'net_weight' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'gross_weight' => ['nullable', 'numeric', 'min:0', 'max:100000', 'gte:net_weight'],
            'weight_unit_id' => [
                'nullable',
                'integer',
                Rule::exists('units', 'id')->where(fn ($query) => $query->where('measurement_id', $massId)),
            ],

            'length' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'width' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'height' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'dimension_unit_id' => [
                'nullable',
                'integer',
                Rule::exists('units', 'id')->where(fn ($query) => $query->where('measurement_id', $lengthId)),
            ],

            'packaging_type_unit_id' => [
                'nullable',
                'integer',
                Rule::exists('units', 'id')->where(fn ($query) => $query->where('measurement_id', $packagingId)),
            ],
            'units_per_case' => ['nullable', 'integer', 'min:1', 'max:100000'],

            'ships_alone' => ['sometimes', 'boolean'],
            'is_fragile' => ['sometimes', 'boolean'],
            'is_stackable' => ['sometimes', 'boolean'],
            'is_hazmat' => ['sometimes', 'boolean'],

            'un_number' => ['nullable', 'string', 'max:20'],
            'hazard_class_option_id' => [
                'nullable',
                'integer',
                $this->optionRule('pkg_hazard_class'),
            ],
            'packing_group_option_id' => [
                'nullable',
                'integer',
                $this->optionRule('pkg_packing_group'),
            ],

            'handling_notes' => ['nullable', 'string', 'max:2000'],

            'contents' => ['nullable', 'array', 'max:50'],
            'contents.*.name' => ['required_with:contents', 'string', 'max:120'],
            'contents.*.qty' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'gross_weight.gte' => 'The gross weight must be greater than or equal to the net weight.',
            'weight_unit_id.exists' => 'Choose a unit of mass for the weight.',
            'dimension_unit_id.exists' => 'Choose a unit of length for the dimensions.',
            'packaging_type_unit_id.exists' => 'Choose a packaging type (box, carton, pallet…).',
        ];
    }

    private function measurementId(string $code): int
    {
        return (int) Measurement::query()->where('code', $code)->value('id');
    }

    private function optionRule(string $definitionCode): mixed
    {
        $definitionIds = Definition::query()
            ->where('code', $definitionCode)
            ->where('kind', 'packaging')
            ->pluck('id')
            ->all();

        return Rule::exists('options', 'id')->whereIn('definition_id', $definitionIds);
    }
}
