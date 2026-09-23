<?php

namespace App\Http\Requests\V1\Feedback;

use App\Enums\FeedbackPriorityEnum;
use App\Enums\FeedbackStatusEnum;
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
            'page_url' => 'nullable|string|max:2048',
            'additional_info' => 'nullable|array',
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
            'status' => FeedbackStatusEnum::NEW->value,
            'priority' => $validated['priority'] ?? FeedbackPriorityEnum::MEDIUM->value,
            'source' => 'api',
            'ip_address' => $this->ip(),
            'user_agent' => $this->header('User-Agent'),
            'page_url' => $validated['page_url'] ?? null,
            'additional_info' => $validated['additional_info'] ?? [],
        ]);
    }
}
