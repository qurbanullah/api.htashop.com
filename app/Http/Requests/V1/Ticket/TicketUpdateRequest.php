<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\Ticket;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TicketUpdateRequest extends FormRequest
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
            'title' => ['sometimes', 'string', 'max:255'],
            'stype' => ['sometimes', 'string', Rule::in(['license', 'question', 'security-issue', 'bug'])],
            'severity' => ['sometimes', 'string', Rule::in(['minor', 'major', 'crash', 'block'])],
            'reproducibility' => ['sometimes', 'string', Rule::in(['always', 'sometimes', 'random', 'have-not-tried', 'unable-to-reproduce', 'not-applicable'])],
            'priority' => ['sometimes', 'string', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'description' => ['sometimes', 'string'],
            // Debug flag from client indicating whether the editor contained <img>
            'client_contains_img' => ['sometimes', 'boolean'],
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
}
