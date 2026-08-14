<?php

namespace App\Http\Requests\V1\Feedback;

use Illuminate\Foundation\Http\FormRequest;

class StoreFeedbackRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Public endpoint
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'type' => 'required|in:feedback,feature_request,suggestion,bug_report',
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'subject' => 'required|string|max:255',
            'message' => 'required|string|min:10|max:5000',
            'priority' => 'nullable|in:low,medium,high,critical',
            'software_name' => 'nullable|string|max:255',
            'software_version' => 'nullable|string|max:100',
            'operating_system' => 'nullable|string|max:255',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'type.required' => 'Feedback type is required.',
            'type.in' => 'Invalid feedback type selected.',
            'name.required' => 'Name is required.',
            'email.required' => 'Email is required.',
            'email.email' => 'Please provide a valid email address.',
            'subject.required' => 'Subject is required.',
            'message.required' => 'Message is required.',
            'message.min' => 'Message must be at least 10 characters.',
            'message.max' => 'Message cannot exceed 5000 characters.',
        ];
    }

    /**
     * Get validated data with additional fields
     */
    public function getValidatedData(): array
    {
        $validated = $this->validated();

        return array_merge($validated, [
            'status' => 'new',
            'priority' => $validated['priority'] ?? 'medium',
            'source' => 'api',
            'ip_address' => $this->ip(),
            'user_agent' => $this->header('User-Agent'),
        ]);
    }
}
