<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\Knowledge;

use App\Enums\KnowledgeSourceEnum;
use App\Enums\KnowledgeStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateKnowledgeEntryRequest extends FormRequest
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
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'body' => ['sometimes', 'required', 'string', 'max:100000'],
            'question' => ['sometimes', 'nullable', 'string', 'max:255'],
            'locale' => ['sometimes', 'string', Rule::in(['*', 'en', 'de', 'ur'])],
            'tags' => ['sometimes', 'nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
            'source_type' => ['sometimes', 'string', Rule::in(KnowledgeSourceEnum::values())],
            'source_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'status' => ['sometimes', 'string', Rule::in(KnowledgeStatusEnum::values())],
            'restricted' => ['sometimes', 'boolean'],
            'priority' => ['sometimes', 'integer', 'between:-100,100'],
            'tenant_id' => ['sometimes', 'nullable', 'integer', 'exists:tenants,id'],
        ];
    }
}
