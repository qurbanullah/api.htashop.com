<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\Ticket;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class TicketStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by policy
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'stype' => ['required', 'string', Rule::in(['license', 'question', 'security-issue', 'bug'])],
            'severity' => ['required', 'string', Rule::in(['minor', 'major', 'crash', 'block'])],
            'reproducibility' => ['required', 'string', Rule::in(['always', 'sometimes', 'random', 'have-not-tried', 'unable-to-reproduce', 'not-applicable'])],
            'priority' => ['required', 'string', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'description' => ['required', 'string'],
            'steps_to_reproduce' => ['nullable', 'string'],
            'additional_information' => ['nullable', 'string'],

            // Polymorphic relationships - arrays of IDs
            'software_ids' => ['nullable', 'array'],
            'software_ids.*' => ['integer', 'exists:softwares,id'],
            'version_ids' => ['nullable', 'array'],
            'version_ids.*' => ['integer', 'exists:versions,id'],
            'ltype_ids' => ['nullable', 'array'],
            'ltype_ids.*' => ['integer', 'exists:ltypes,id'],
            'package_ids' => ['nullable', 'array'],
            'package_ids.*' => ['integer', 'exists:packages,id'],
        ];
    }

    /**
     * Get custom attribute names for error messages.
     */
    public function attributes(): array
    {
        return [
            'stype' => 'support type',
            'software_ids' => 'software',
            'version_ids' => 'versions',
            'ltype_ids' => 'license types',
            'package_ids' => 'packages',
        ];
    }

    /**
     * Get custom error messages.
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Please provide a title for your ticket.',
            'stype.required' => 'Please select a support type.',
            'severity.required' => 'Please select the severity level.',
            'reproducibility.required' => 'Please indicate how often the issue occurs.',
            'priority.required' => 'Please set a priority level.',
            'description.required' => 'Please provide a description of your issue.',
        ];
    }

    /**
     * Get validated data with defaults.
     */
    public function validated($key = null, $default = null): array
    {
        $data = parent::validated($key, $default);

        // Add user_id
        $data['user_id'] = Auth::id();

        // Set default status
        $data['status'] = 'open';

        // Set visibility defaults
        $data['is_visible'] = true;
        $data['is_resolved'] = false;
        $data['is_archived'] = false;
        $data['is_locked'] = false;

        return $data;
    }
}
