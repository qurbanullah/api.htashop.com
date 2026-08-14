<?php

namespace App\Http\Requests\V1\Eula;

use App\Enums\EulaStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEulaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by Policy
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'version' => ['sometimes', 'required', 'string', 'max:50'],
            'software_id' => ['nullable', 'integer', 'exists:softwares,id'],
            'version_id' => ['nullable', 'integer', 'exists:versions,id'],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'content' => ['sometimes', 'required', 'string'],
            'status' => ['sometimes', 'string', Rule::in(EulaStatus::values())],
            'effective_date' => ['nullable', 'date'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'version.required' => 'EULA version is required',
            'title.required' => 'EULA title is required',
            'content.required' => 'EULA content is required',
        ];
    }
}
