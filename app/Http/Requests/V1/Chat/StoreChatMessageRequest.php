<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\Chat;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreChatMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'min:1', 'max:2000'],
            'locale' => ['nullable', 'string', Rule::in(['en', 'de', 'ur'])],
        ];
    }

    public function message(): string
    {
        return trim((string) $this->input('message', ''));
    }

    public function locale(): string
    {
        $locale = (string) $this->input('locale', '');

        if ($locale === '') {
            $locale = (string) app()->getLocale();
        }

        return in_array($locale, ['en', 'de', 'ur'], true) ? $locale : 'en';
    }
}
