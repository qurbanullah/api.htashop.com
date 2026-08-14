<?php

namespace App\Http\Requests\V1\Forum;

use Illuminate\Foundation\Http\FormRequest;

class ForumCommentStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'min:1', 'max:5000'],
            'parent_id' => ['nullable', 'integer', 'exists:forum_comments,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'body.required' => 'Comment text is required.',
            'body.max' => 'Comment must not exceed 5000 characters.',
            'parent_id.exists' => 'The parent comment does not exist.',
        ];
    }
}
