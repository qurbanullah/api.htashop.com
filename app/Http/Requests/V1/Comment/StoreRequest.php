<?php

namespace App\Http\Requests\V1\Comment;

use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [

            'content' => 'required|string|max:5000',
            'is_internal' => 'nullable|boolean',
            'attachments' => 'nullable|array',
        
        ];
    }
}
