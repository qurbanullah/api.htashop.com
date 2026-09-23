<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\Knowledge;

use App\Enums\KnowledgeSourceEnum;
use App\Enums\KnowledgeStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreKnowledgeEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:100000'],
            'question' => ['nullable', 'string', 'max:255'],
            'locale' => ['nullable', 'string', Rule::in(['*', 'en', 'de', 'ur'])],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
            'source_type' => ['nullable', 'string', Rule::in(KnowledgeSourceEnum::values())],
            'source_url' => ['nullable', 'string', 'max:2048'],
            'status' => ['nullable', 'string', Rule::in(KnowledgeStatusEnum::values())],
            'restricted' => ['nullable', 'boolean'],
            'priority' => ['nullable', 'integer', 'between:-100,100'],
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
        ];
    }
}
