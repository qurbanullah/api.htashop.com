<?php

namespace App\Http\Requests\V1\Eula;

use Illuminate\Foundation\Http\FormRequest;

class RecordConsentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'eula_uuid' => ['required', 'string', 'exists:eulas,uuid'],
            'consentable_type' => ['nullable', 'string'],
            'consentable_id' => ['nullable', 'integer'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'eula_uuid.required' => 'EULA identifier is required',
            'eula_uuid.exists' => 'Invalid EULA identifier',
        ];
    }
}
