<?php

namespace App\Http\Requests\V1\Shipment;

use Illuminate\Foundation\Http\FormRequest;

abstract class ShipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    protected function shipmentRules(): array
    {
        return [
            'warehouse_id' => ['sometimes', 'nullable', 'integer', 'exists:warehouses,id'],
            'carrier' => ['sometimes', 'nullable', 'string', 'max:30'],
            'service' => ['sometimes', 'nullable', 'string', 'max:80'],
            'tracking_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'tracking_url' => ['sometimes', 'nullable', 'url', 'max:255'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Lets the operator create an empty shipment and immediately fill it with
     * the planner's suggested boxes in one request.
     *
     * @return array<string, mixed>
     */
    protected function autoPackRules(): array
    {
        return ['auto_pack' => ['sometimes', 'boolean']];
    }

    /**
     * @return array<string, mixed>
     */
    protected function itemRules(bool $required): array
    {
        return [
            'items' => $required
                ? ['required', 'array', 'min:1', 'max:200']
                : ['sometimes', 'array', 'min:1', 'max:200'],
            'items.*.order_item_id' => ['required', 'integer', 'distinct'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001', 'max:100000'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function parcelRules(): array
    {
        return [
            'parcels' => ['sometimes', 'nullable', 'array', 'max:50'],
            'parcels.*.packaging_template_id' => [
                'sometimes',
                'nullable',
                'integer',
                'exists:packaging_templates,id',
            ],
            'parcels.*.sequence' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:999'],
            'parcels.*.length_cm' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100000'],
            'parcels.*.width_cm' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100000'],
            'parcels.*.height_cm' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100000'],
            'parcels.*.weight_kg' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100000'],
            'parcels.*.contains_hazmat' => ['sometimes', 'boolean'],
            'parcels.*.tracking_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'parcels.*.notes' => ['sometimes', 'nullable', 'string', 'max:1000'],

            // Which order lines (and how much of each) sit inside this box.
            'parcels.*.items' => ['sometimes', 'array', 'max:200'],
            'parcels.*.items.*.order_item_id' => ['required', 'integer'],
            'parcels.*.items.*.quantity' => ['required', 'numeric', 'min:0.001', 'max:100000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => 'Choose at least one item to include in this shipment.',
            'items.*.order_item_id.distinct' => 'Each order line may only appear once per shipment.',
            'items.*.quantity.min' => 'A shipped quantity must be greater than zero.',
            'parcels.*.packaging_template_id.exists' => 'The selected packaging does not exist.',
            'parcels.*.items.*.quantity.min' => 'A packed quantity must be greater than zero.',
            'warehouse_id.exists' => 'The selected warehouse does not exist.',
        ];
    }
}
