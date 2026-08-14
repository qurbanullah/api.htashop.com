<?php

namespace App\Http\Requests\V1\Comment;

use Illuminate\Foundation\Http\FormRequest;

class StoreRequest6a160a9cbb4c6 extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [

            'content' => 'required|string|max:5000',
            'attachments' => 'nullable|array',
        
        ];
    }
}
