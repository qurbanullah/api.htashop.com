<?php

namespace App\Http\Requests\V1\Variant;

use App\Models\Variant;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;

class VariantUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['sometimes', 'integer', 'exists:products,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'seller_sku' => ['nullable', 'string', 'max:100'],
            'status' => ['sometimes', 'string', 'max:100'],
            'summary' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'configuration' => ['nullable', 'array'],
            'is_default' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'metadata' => ['nullable', 'array'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
            'feature_ids' => ['nullable', 'array'],
            'feature_ids.*' => ['integer', 'exists:features,id'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'exists:tags,id'],
        ];
    }

    protected function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator): void {
            $variant = Variant::query()->with('product')->where('uuid', $this->route('uuid'))->first();

            if (!$variant || !$this->filled('product_id')) {
                return;
            }

            $product = Product::query()->find($this->input('product_id'));

            if (!$product) {
                return;
            }

            if ($product->tenant_id !== $variant->product->tenant_id) {
                $validator->errors()->add('product_id', 'Variants cannot be moved across tenants.');
            }

            if ($product->organization_id !== $variant->product->organization_id) {
                $validator->errors()->add('product_id', 'Variants cannot be moved across organizations.');
            }
        });
    }
}
