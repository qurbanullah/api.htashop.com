<?php

namespace App\Http\Requests\V1\Forum;

use App\Enums\ForumPostStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ForumPostStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'topic_uuid' => ['required', 'string', 'exists:forum_topics,uuid'],
            'title' => ['required', 'string', 'min:5', 'max:300'],
            'body' => ['required', 'string', 'min:10', 'max:50000'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.min' => 'The title must be at least 5 characters.',
            'body.min' => 'The post content must be at least 10 characters.',
            'topic_uuid.exists' => 'The selected topic does not exist.',
        ];
    }
}
