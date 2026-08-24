<?php

namespace App\Http\Requests\V1\Gdpr;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGdprConsentRequest extends FormRequest
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
            'consent_token' => ['required', 'string', 'max:64'],
            'categories' => ['required', 'array'],
            'categories.necessary' => ['required', 'boolean'],
            'categories.preferences' => ['required', 'boolean'],
            'categories.analytics' => ['required', 'boolean'],
            'categories.marketing' => ['required', 'boolean'],
            'policy_version' => ['required', 'string', 'max:20'],
            'source' => ['required', Rule::in(['banner', 'settings', 'account'])],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'consent_token.required' => 'A consent identifier is required',
            'categories.required' => 'Consent categories are required',
            'categories.necessary.required' => 'The necessary category is required',
            'policy_version.required' => 'The consent policy version is required',
            'source.in' => 'Invalid consent source',
        ];
    }
}
