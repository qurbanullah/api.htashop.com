<?php

namespace App\Http\Requests\V1\Dam;

use Illuminate\Foundation\Http\FormRequest;

class ReadDamOwnersRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Assume policy handled in controller or implicitly allowed
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => 'required|string|in:product,variant,tenant,organization',
            'query' => 'sometimes|string|max:255',
            'limit' => 'sometimes|integer|min:1|max:25',
        ];
    }
}
