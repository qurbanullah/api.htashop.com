<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\Chat;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreChatFeedbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'feedback' => ['required', 'string', Rule::in(['helpful', 'unhelpful'])],
            'comment' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
