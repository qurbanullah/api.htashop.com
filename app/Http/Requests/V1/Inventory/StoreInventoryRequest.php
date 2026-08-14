<?php

namespace App\Http\Requests\V1\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class StoreInventoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'stockable_type' => ['required', 'string'],
            'stockable_id' => ['required', 'integer'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'sku' => ['nullable', 'string', 'max:100'],
            'quantity' => ['nullable', 'numeric', 'min:0'],
            'reserved' => ['nullable', 'numeric', 'min:0'],
            'low_stock_threshold' => ['nullable', 'numeric', 'min:0'],
            'track_inventory' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
