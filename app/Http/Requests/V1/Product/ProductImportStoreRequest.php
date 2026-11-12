<?php

namespace App\Http\Requests\V1\Product;

use App\Enums\ProductImportKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `parse` runs inside the request, while `apply` is queued in chunks. The
 * upload therefore has to be bounded: a 20 MB / 50,000-row file keeps parsing
 * inside the request timeout and the memory limit.
 */
class ProductImportStoreRequest extends FormRequest
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
        return [
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,ods', 'max:20480'],
            'kind' => ['nullable', 'string', Rule::in(ProductImportKind::ALL)],
            'download_images' => ['nullable', 'boolean'],
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
            'organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
            'warehouse' => ['nullable', 'string', 'max:100'],
            'sheet' => ['nullable', 'string', 'max:100'],
            'create_missing' => ['nullable', 'boolean'],
            'dry_run' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.mimes' => 'Upload a .csv, .xlsx or .ods file.',
            'file.max' => 'The file must be 20 MB or smaller.',
        ];
    }
}
