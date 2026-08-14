<?php

namespace App\Http\Requests\V1\Feedback;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

/**
 * Request validation for creating feedback comments
 */
class StoreFeedbackCommentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Only authenticated users (admins/staff) can add comments
        return Auth::check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'min:10', 'max:5000'],
            'is_internal' => ['sometimes', 'boolean'],
            'metadata' => ['sometimes', 'array'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'content.required' => 'Comment content is required.',
            'content.min' => 'Comment must be at least 10 characters.',
            'content.max' => 'Comment cannot exceed 5000 characters.',
        ];
    }

    /**
     * Get validated data with user ID
     *
     * @return array
     */
    public function getValidatedData(): array
    {
        $validated = $this->validated();

        return [
            'content' => $validated['content'],
            'user_id' => Auth::id(),
            'is_internal' => $validated['is_internal'] ?? true,
            'metadata' => $validated['metadata'] ?? [],
        ];
    }
}
