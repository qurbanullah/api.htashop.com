<?php

namespace App\Http\Requests\V1\Contact;

use Illuminate\Foundation\Http\FormRequest;

class ReplyContactMessageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() && $this->user()->hasRole('admin');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'reply_message' => 'required|string|min:10|max:5000',
            'reply_subject' => 'nullable|string|max:255',
            'mark_as_replied' => 'nullable|boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'reply_message.required' => 'Reply message is required.',
            'reply_message.min' => 'Reply message must be at least 10 characters.',
            'reply_message.max' => 'Reply message cannot exceed 5000 characters.',
        ];
    }
}
