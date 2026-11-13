<?php

namespace App\Http\Requests\V1\Quote;

use App\Enums\QuoteStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QuoteStatusUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:'.implode(',', QuoteStatus::ALL)],
            'reason' => [
                Rule::requiredIf(fn (): bool => in_array(
                    $this->input('status'),
                    [QuoteStatus::CANCELLED, QuoteStatus::DECLINED],
                    true,
                )),
                'nullable',
                'string',
                'max:255',
            ],
        ];
    }
}
