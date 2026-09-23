<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\Newsletter;

use Illuminate\Foundation\Http\FormRequest;

class SubscribeNewsletterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'consent' => ['sometimes', 'accepted'],
            'source_page' => ['nullable', 'string', 'max:2048'],
        ];
    }
}
