<?php

namespace App\Http\Requests\V1\Forum;

use App\Enums\ForumPostStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ForumPostUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'title' => ['sometimes', 'required', 'string', 'min:5', 'max:300'],
            'body' => ['sometimes', 'required', 'string', 'min:10', 'max:50000'],
            'topic_id' => ['sometimes', 'integer', 'exists:forum_topics,id'],
        ];

        // Only admins can change moderation fields
        if ($this->user() && $this->user()->hasAnyRole(['super-admin', 'admin'])) {
            $rules['status'] = ['sometimes', Rule::in(ForumPostStatusEnum::values())];
            $rules['is_pinned'] = ['sometimes', 'boolean'];
            $rules['is_locked'] = ['sometimes', 'boolean'];
            $rules['is_featured'] = ['sometimes', 'boolean'];
        }

        return $rules;
    }
}
