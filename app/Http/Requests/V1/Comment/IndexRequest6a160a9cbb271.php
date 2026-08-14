<?php

namespace App\Http\Requests\V1\Comment;

use Illuminate\Foundation\Http\FormRequest;

class IndexRequest6a160a9cbb271 extends FormRequest
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
            'content' => 'required|string|max:5000',
            'is_internal' => 'nullable|boolean',
            'attachments' => 'nullable|array',
            'revision_ids' => 'nullable|array',
            'revision_ids.*' => 'integer|exists:revisions,id',
        
        ];
    }
}
