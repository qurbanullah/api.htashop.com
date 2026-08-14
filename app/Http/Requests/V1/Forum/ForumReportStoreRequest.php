<?php

namespace App\Http\Requests\V1\Forum;

use App\Enums\ForumReportReasonEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ForumReportStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reportable_type' => ['required', 'string', Rule::in(['post', 'comment'])],
            'reportable_id' => ['required', 'integer'],
            'reason' => ['required', Rule::in(ForumReportReasonEnum::values())],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'reportable_type.in' => 'Report type must be either "post" or "comment".',
            'reason.in' => 'Please select a valid report reason.',
        ];
    }
}
