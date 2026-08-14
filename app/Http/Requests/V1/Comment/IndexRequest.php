<?php

namespace App\Http\Requests\V1\Comment;

use Illuminate\Foundation\Http\FormRequest;

class IndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [

            'commentable_type' => 'required|string',
            'commentable_id' => 'required|integer',
            'include_internal' => 'nullable|boolean',
        
        ];
    }
}
