<?php

namespace App\Http\Requests\V1\Inventory;

use App\Models\Inventory;
use App\Models\Warehouse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class UpdateInventoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'sku' => ['nullable', 'string', 'max:100'],
            'quantity' => ['sometimes', 'numeric', 'min:0'],
            'reserved' => ['sometimes', 'numeric', 'min:0'],
            'low_stock_threshold' => ['nullable', 'numeric', 'min:0'],
            'track_inventory' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    protected function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator): void {
            if (! $this->filled('warehouse_id')) {
                return;
            }

            $routeUuid = $this->route()?->parameter('uuid');

            $inventory = Inventory::query()->where('uuid', $routeUuid)->first();

            if (! $inventory) {
                return;
            }

            $warehouse = Warehouse::query()->find($this->input('warehouse_id'));

            if ($warehouse && (int) $warehouse->tenant_id !== (int) $inventory->tenant_id) {
                $validator->errors()->add('warehouse_id', 'The selected warehouse does not belong to the inventory tenant.');
            }
        });
    }
}
