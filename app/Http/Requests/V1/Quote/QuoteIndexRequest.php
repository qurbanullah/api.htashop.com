<?php

namespace App\Http\Requests\V1\Quote;

use App\Enums\QuoteStatus;
use Illuminate\Foundation\Http\FormRequest;

class QuoteIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', 'in:'.implode(',', QuoteStatus::ALL)],
            'priority' => ['nullable', 'string', 'in:low,normal,high,urgent'],
            'assigned_to' => ['nullable', 'string', 'max:32'],
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
            'organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
            'search' => ['nullable', 'string', 'max:255'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'expiring_within_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'sort' => ['nullable', 'string', 'in:newest,oldest,oldest_unanswered'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
