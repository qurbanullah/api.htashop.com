<?php

namespace App\Http\Requests\V1\Comment;

use Illuminate\Foundation\Http\FormRequest;

class ReplyRequest6a160a9cbb75a extends FormRequest
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
        
        ];
    }
}
