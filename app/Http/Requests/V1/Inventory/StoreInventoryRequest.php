<?php

namespace App\Http\Requests\V1\Inventory;

use App\Models\Product;
use App\Models\Variant;
use App\Models\Warehouse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreInventoryRequest extends FormRequest
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
            'stockable_type' => ['required', 'string', 'in:product,variant'],
            'stockable_id' => ['required', 'integer', 'min:1'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'sku' => ['nullable', 'string', 'max:100'],
            'quantity' => ['nullable', 'numeric', 'min:0'],
            'reserved' => ['nullable', 'numeric', 'min:0'],
            'low_stock_threshold' => ['nullable', 'numeric', 'min:0'],
            'track_inventory' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $type = $this->input('stockable_type');

        $this->merge([
            'stockable_type' => match ($type) {
                Product::class => 'product',
                Variant::class => 'variant',
                default => $type,
            },
        ]);
    }

    protected function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator): void {
            $stockable = $this->resolveStockable();

            if (! $stockable) {
                $validator->errors()->add('stockable_id', 'The selected stockable record could not be found.');

                return;
            }

            if (! $this->filled('warehouse_id')) {
                return;
            }

            $warehouse = Warehouse::query()->find($this->input('warehouse_id'));

            if ($warehouse && (int) $warehouse->tenant_id !== (int) $stockable->tenant_id) {
                $validator->errors()->add('warehouse_id', 'The selected warehouse does not belong to the stockable tenant.');
            }
        });
    }

    private function resolveStockable(): Product|Variant|null
    {
        return match ($this->input('stockable_type')) {
            'product' => Product::query()->find($this->input('stockable_id')),
            'variant' => Variant::query()->find($this->input('stockable_id')),
            default => null,
        };
    }
}
